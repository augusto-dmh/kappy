<?php

use Modules\GitHubApp\Contracts\ScmDriver;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;
use Modules\Review\Contracts\Reviewer;
use Modules\Review\Dto\DraftFinding;
use Modules\Review\Dto\DraftReview;
use Modules\Review\Dto\ReviewInput;
use Modules\Review\Dto\ReviewSummary;
use Modules\Review\Dto\Telemetry;
use Modules\Review\Enums\FindingCategory;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\FindingStatus;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Enums\RiskLevel;
use Modules\Review\Jobs\ProcessReview;
use Modules\Review\Models\Review;

/**
 * A test double for the ScmDriver contract that returns a canned diff and
 * records every call so the pipeline's "no GitHub writes" invariant can be
 * asserted without a real HTTP call.
 */
class FakeScmDriverForProcessReview implements ScmDriver
{
    /** @var list<array<string, mixed>> */
    public array $diffCalls = [];

    public array $postCommentCalls = [];

    public array $checkRunCalls = [];

    public function __construct(public string $diff = "diff --git a/a.php b/a.php\n+\$x = 1;\n") {}

    public function pullRequest(int $installationId, string $repositoryFullName, int $pullRequestNumber): array
    {
        return [];
    }

    public function diff(int $installationId, string $repositoryFullName, int $pullRequestNumber): string
    {
        $this->diffCalls[] = compact('installationId', 'repositoryFullName', 'pullRequestNumber');

        return $this->diff;
    }

    public function comments(int $installationId, string $repositoryFullName, int $pullRequestNumber): array
    {
        return [];
    }

    public function postComment(int $installationId, string $repositoryFullName, int $pullRequestNumber, string $body, ?string $path = null, ?int $line = null, ?string $commitSha = null): int
    {
        $this->postCommentCalls[] = compact('installationId', 'repositoryFullName', 'pullRequestNumber', 'body', 'path', 'line', 'commitSha');

        return count($this->postCommentCalls);
    }

    public function checkRun(int $installationId, string $repositoryFullName, string $headSha, string $name, string $summary): int
    {
        $this->checkRunCalls[] = compact('installationId', 'repositoryFullName', 'headSha', 'name', 'summary');

        return count($this->checkRunCalls);
    }
}

/**
 * A test double for the Reviewer contract that returns a canned DraftReview
 * and records the input it was called with.
 */
class FakeReviewerForProcessReview implements Reviewer
{
    /** @var list<ReviewInput> */
    public array $calls = [];

    public function __construct(private ?Closure $onGenerate = null) {}

    public function generate(ReviewInput $input): DraftReview
    {
        $this->calls[] = $input;

        if ($this->onGenerate !== null) {
            return ($this->onGenerate)($input);
        }

        return new DraftReview(
            summary: new ReviewSummary(
                overview: 'Adds a widget endpoint.',
                walkthrough: 'A controller and route were introduced.',
                riskLevel: RiskLevel::Medium,
            ),
            findings: [
                new DraftFinding(
                    category: FindingCategory::Security,
                    severity: FindingSeverity::High,
                    path: 'app/Http/Controllers/WidgetController.php',
                    line: 42,
                    title: 'Unvalidated request input',
                    message: 'The request input is used without validation.',
                    suggestion: 'Validate the payload with a FormRequest.',
                    agentPrompt: null,
                    confidence: 80,
                ),
            ],
            telemetry: new Telemetry(
                model: 'claude-opus-4-8',
                inputTokens: 1200,
                outputTokens: 300,
                cachedTokens: 1000,
                costCents: null,
                durationMs: 4200,
            ),
        );
    }
}

function queuedReviewFixture(array $attributes = []): Review
{
    $account = Account::factory()->create();
    $installation = Installation::factory()->create(['account_id' => $account->id, 'github_installation_id' => 987654]);
    $repository = Repository::factory()->create(['installation_id' => $installation->id, 'full_name' => 'acme/widgets']);
    $pullRequest = PullRequest::factory()->create([
        'repository_id' => $repository->id,
        'github_pr_number' => 7,
        'title' => 'Add widget endpoint',
        'author_login' => 'octocat',
        'base_sha' => str_repeat('a', 40),
    ]);

    return Review::factory()->create([
        'pull_request_id' => $pullRequest->id,
        'head_sha' => str_repeat('b', 40),
        'status' => ReviewStatus::Queued,
        ...$attributes,
    ]);
}

test('the happy path fetches the diff, generates, persists, and ends ready to post', function () {
    $review = queuedReviewFixture();

    $scmDriver = new FakeScmDriverForProcessReview;
    $statusAtGenerate = null;
    $reviewer = new FakeReviewerForProcessReview(function (ReviewInput $input) use ($review, &$statusAtGenerate) {
        $statusAtGenerate = $review->fresh()->status;

        return new DraftReview(
            summary: new ReviewSummary(
                overview: 'Adds a widget endpoint.',
                walkthrough: 'A controller and route were introduced.',
                riskLevel: RiskLevel::Medium,
            ),
            findings: [
                new DraftFinding(
                    category: FindingCategory::Security,
                    severity: FindingSeverity::High,
                    path: 'app/Http/Controllers/WidgetController.php',
                    line: 42,
                    title: 'Unvalidated request input',
                    message: 'The request input is used without validation.',
                    suggestion: 'Validate the payload with a FormRequest.',
                    agentPrompt: null,
                    confidence: 80,
                ),
            ],
            telemetry: new Telemetry(
                model: 'claude-opus-4-8',
                inputTokens: 1200,
                outputTokens: 300,
                cachedTokens: 1000,
                costCents: null,
                durationMs: 4200,
            ),
        );
    });
    app()->instance(ScmDriver::class, $scmDriver);
    app()->instance(Reviewer::class, $reviewer);

    ProcessReview::dispatchSync($review->id);

    $review->refresh();

    expect($statusAtGenerate)->toBe(ReviewStatus::Generating)
        ->and($review->status)->toBe(ReviewStatus::ReadyToPost)
        ->and($review->started_at)->not->toBeNull()
        ->and($review->summary_overview)->toBe('Adds a widget endpoint.')
        ->and($review->summary_risk_level)->toBe(RiskLevel::Medium)
        ->and($review->findings)->toHaveCount(1)
        ->and($review->findings->first()->status)->toBe(FindingStatus::Approved);

    expect($scmDriver->diffCalls)->toHaveCount(1)
        ->and($scmDriver->diffCalls[0])->toBe([
            'installationId' => 987654,
            'repositoryFullName' => 'acme/widgets',
            'pullRequestNumber' => 7,
        ]);

    expect($reviewer->calls)->toHaveCount(1);
    $input = $reviewer->calls[0];
    expect($input->title)->toBe('Add widget endpoint')
        ->and($input->author)->toBe('octocat')
        ->and($input->baseSha)->toBe(str_repeat('a', 40))
        ->and($input->headSha)->toBe(str_repeat('b', 40))
        ->and($input->repositoryFullName)->toBe('acme/widgets')
        ->and($input->diff)->toBe($scmDriver->diff);

    expect($scmDriver->postCommentCalls)->toBeEmpty()
        ->and($scmDriver->checkRunCalls)->toBeEmpty();
});

test('the run transitions through fetching before the generate call', function () {
    $review = queuedReviewFixture();

    $probe = (object) ['statusAtDiff' => null];
    $scmDriver = new class($review, $probe) implements ScmDriver
    {
        public function __construct(
            private Review $review,
            private object $probe,
        ) {}

        public function pullRequest(int $installationId, string $repositoryFullName, int $pullRequestNumber): array
        {
            return [];
        }

        public function diff(int $installationId, string $repositoryFullName, int $pullRequestNumber): string
        {
            $this->probe->statusAtDiff = $this->review->fresh()->status;

            return "diff --git a/a.php b/a.php\n+\$x = 1;\n";
        }

        public function comments(int $installationId, string $repositoryFullName, int $pullRequestNumber): array
        {
            return [];
        }

        public function postComment(int $installationId, string $repositoryFullName, int $pullRequestNumber, string $body, ?string $path = null, ?int $line = null, ?string $commitSha = null): int
        {
            return 1;
        }

        public function checkRun(int $installationId, string $repositoryFullName, string $headSha, string $name, string $summary): int
        {
            return 1;
        }
    };

    app()->instance(ScmDriver::class, $scmDriver);
    app()->instance(Reviewer::class, new FakeReviewerForProcessReview);

    ProcessReview::dispatchSync($review->id);

    expect($probe->statusAtDiff)->toBe(ReviewStatus::Fetching)
        ->and($review->fresh()->status)->toBe(ReviewStatus::ReadyToPost);
});

test('an oversized diff is skipped with a reason and creates no findings', function () {
    config()->set('kappy.review.max_pr_diff_lines', 2);

    $review = queuedReviewFixture();

    app()->instance(ScmDriver::class, new FakeScmDriverForProcessReview("line1\nline2\nline3"));
    $reviewer = new FakeReviewerForProcessReview;
    app()->instance(Reviewer::class, $reviewer);

    ProcessReview::dispatchSync($review->id);

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::Skipped)
        ->and($review->failure_reason)->toBe('diff_exceeds_limit')
        ->and($review->failure_reason)->not->toContain('line1')
        ->and($review->findings)->toHaveCount(0)
        ->and($reviewer->calls)->toBeEmpty();
});

test('a hard failure during generate marks the review failed without a diff in the reason', function () {
    $review = queuedReviewFixture();

    app()->instance(ScmDriver::class, new FakeScmDriverForProcessReview("diff --git a/a.php b/a.php\n+very secret customer content\n"));
    app()->instance(Reviewer::class, new FakeReviewerForProcessReview(function () {
        throw new RuntimeException('provider_timeout');
    }));

    ProcessReview::dispatchSync($review->id);

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::Failed)
        ->and($review->failure_reason)->toBe('provider_timeout')
        ->and($review->failure_reason)->not->toContain('secret customer content')
        ->and($review->findings)->toHaveCount(0);
});

test('unsafe exception messages are mapped to a generic failure reason', function () {
    $review = queuedReviewFixture();

    app()->instance(ScmDriver::class, new FakeScmDriverForProcessReview);
    app()->instance(Reviewer::class, new FakeReviewerForProcessReview(function () {
        throw new RuntimeException('content policy rejected prompt: diff --git a/secret.php b/secret.php');
    }));

    ProcessReview::dispatchSync($review->id);

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::Failed)
        ->and($review->failure_reason)->toBe('review_failed')
        ->and($review->failure_reason)->not->toContain('secret.php')
        ->and($review->failure_reason)->not->toContain('diff --git');
});

test('a hard failure fetching the diff marks the review failed', function () {
    $review = queuedReviewFixture();

    app()->instance(ScmDriver::class, new class implements ScmDriver
    {
        public function pullRequest(int $installationId, string $repositoryFullName, int $pullRequestNumber): array
        {
            return [];
        }

        public function diff(int $installationId, string $repositoryFullName, int $pullRequestNumber): string
        {
            throw new RuntimeException('scm_unreachable');
        }

        public function comments(int $installationId, string $repositoryFullName, int $pullRequestNumber): array
        {
            return [];
        }

        public function postComment(int $installationId, string $repositoryFullName, int $pullRequestNumber, string $body, ?string $path = null, ?int $line = null, ?string $commitSha = null): int
        {
            return 1;
        }

        public function checkRun(int $installationId, string $repositoryFullName, string $headSha, string $name, string $summary): int
        {
            return 1;
        }
    });
    app()->instance(Reviewer::class, new FakeReviewerForProcessReview);

    ProcessReview::dispatchSync($review->id);

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::Failed)
        ->and($review->failure_reason)->toBe('scm_unreachable')
        ->and($review->findings)->toHaveCount(0);
});

test('a review that is not queued is left unchanged', function () {
    $review = queuedReviewFixture(['status' => ReviewStatus::ReadyToPost, 'summary_overview' => 'Existing summary.']);

    $scmDriver = new FakeScmDriverForProcessReview;
    $reviewer = new FakeReviewerForProcessReview;
    app()->instance(ScmDriver::class, $scmDriver);
    app()->instance(Reviewer::class, $reviewer);

    ProcessReview::dispatchSync($review->id);

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::ReadyToPost)
        ->and($review->summary_overview)->toBe('Existing summary.')
        ->and($review->started_at)->toBeNull()
        ->and($scmDriver->diffCalls)->toBeEmpty()
        ->and($reviewer->calls)->toBeEmpty();
});

test('a second handle after the row was claimed is a no-op', function () {
    $review = queuedReviewFixture(['status' => ReviewStatus::Fetching, 'started_at' => now()]);

    $scmDriver = new FakeScmDriverForProcessReview;
    $reviewer = new FakeReviewerForProcessReview;
    app()->instance(ScmDriver::class, $scmDriver);
    app()->instance(Reviewer::class, $reviewer);

    ProcessReview::dispatchSync($review->id);

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::Fetching)
        ->and($scmDriver->diffCalls)->toBeEmpty()
        ->and($reviewer->calls)->toBeEmpty();
});

test('uniqueId is the review id so dispatch dedupes per review', function () {
    $job = new ProcessReview('01REVIEWUNIQUEID0000000000');

    expect($job->uniqueId())->toBe('01REVIEWUNIQUEID0000000000')
        ->and($job->reviewId)->toBe('01REVIEWUNIQUEID0000000000');
});

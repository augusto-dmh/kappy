<?php

use Illuminate\Support\Facades\File;
use Modules\GitHubApp\Contracts\ScmDriver;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\FindingStatus;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Jobs\PostReview;
use Modules\Review\Models\Finding;
use Modules\Review\Models\Review;

class FakeScmDriverForPostReview implements ScmDriver
{
    public array $postCommentCalls = [];

    public array $checkRunCalls = [];

    public function __construct(
        public ?Throwable $checkRunException = null,
    ) {}

    public function pullRequest(int $installationId, string $repositoryFullName, int $pullRequestNumber): array
    {
        return [];
    }

    public function diff(int $installationId, string $repositoryFullName, int $pullRequestNumber): string
    {
        return '';
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
        if ($this->checkRunException !== null) {
            throw $this->checkRunException;
        }

        $this->checkRunCalls[] = compact('installationId', 'repositoryFullName', 'headSha', 'name', 'summary');

        return 77;
    }
}

function postableReviewFixture(array $attributes = [], array $findings = []): Review
{
    $account = Account::factory()->create();
    $installation = Installation::factory()->create(['account_id' => $account->id, 'github_installation_id' => 42]);
    $repository = Repository::factory()->create(['installation_id' => $installation->id, 'full_name' => 'acme/widgets']);
    $pullRequest = PullRequest::factory()->create([
        'repository_id' => $repository->id,
        'github_pr_number' => 5,
    ]);

    $review = Review::factory()->readyToPost()->create([
        'pull_request_id' => $pullRequest->id,
        'head_sha' => str_repeat('c', 40),
        'summary_overview' => 'Adds a widget.',
        'summary_walkthrough' => 'A controller was added.',
        ...$attributes,
    ]);

    foreach ($findings as $finding) {
        Finding::factory()->create([
            'review_id' => $review->id,
            'status' => FindingStatus::Approved,
            ...$finding,
        ]);
    }

    return $review->fresh(['findings', 'pullRequest.repository.installation']);
}

test('the happy path claims posting then completes with github ids and finished_at', function () {
    $review = postableReviewFixture(findings: [[
        'severity' => FindingSeverity::High,
        'path' => 'app/Widget.php',
        'line' => 10,
        'title' => 'Missing validation',
        'message' => 'Validate the payload.',
    ]]);

    $probe = (object) ['statusAtFirstWrite' => null];
    $scm = new class($review, $probe) implements ScmDriver
    {
        public array $postCommentCalls = [];

        public array $checkRunCalls = [];

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
            return '';
        }

        public function comments(int $installationId, string $repositoryFullName, int $pullRequestNumber): array
        {
            return [];
        }

        public function postComment(int $installationId, string $repositoryFullName, int $pullRequestNumber, string $body, ?string $path = null, ?int $line = null, ?string $commitSha = null): int
        {
            $this->probe->statusAtFirstWrite ??= $this->review->fresh()->status;
            $this->postCommentCalls[] = compact('path');

            return count($this->postCommentCalls);
        }

        public function checkRun(int $installationId, string $repositoryFullName, string $headSha, string $name, string $summary): int
        {
            return 77;
        }
    };

    app()->instance(ScmDriver::class, $scm);

    PostReview::dispatchSync($review->id);

    $review->refresh();

    expect($probe->statusAtFirstWrite)->toBe(ReviewStatus::Posting)
        ->and($review->status)->toBe(ReviewStatus::Completed)
        ->and($review->finished_at)->not->toBeNull()
        ->and($review->summary_comment_id)->not->toBeNull()
        ->and($review->github_check_run_id)->toBe(77)
        ->and($review->findings->first()->status)->toBe(FindingStatus::Posted);
});

test('a review already posting is continued rather than no-opped', function () {
    $review = postableReviewFixture(['status' => ReviewStatus::Posting]);
    app()->instance(ScmDriver::class, new FakeScmDriverForPostReview);

    PostReview::dispatchSync($review->id);

    expect($review->fresh()->status)->toBe(ReviewStatus::Completed)
        ->and($review->fresh()->summary_comment_id)->not->toBeNull();
});

test('a review that is not ready to post or posting is left unchanged', function (ReviewStatus $status) {
    $review = postableReviewFixture(['status' => $status, 'summary_overview' => 'Existing summary.']);
    $scm = new FakeScmDriverForPostReview;
    app()->instance(ScmDriver::class, $scm);

    PostReview::dispatchSync($review->id);

    expect($review->fresh()->status)->toBe($status)
        ->and($review->fresh()->summary_comment_id)->toBeNull()
        ->and($scm->postCommentCalls)->toBeEmpty()
        ->and($scm->checkRunCalls)->toBeEmpty();
})->with([
    ReviewStatus::Queued,
    ReviewStatus::Completed,
    ReviewStatus::Failed,
    ReviewStatus::Skipped,
]);

test('a hard scm failure marks the review failed and keeps ids already stored', function () {
    $review = postableReviewFixture();
    app()->instance(ScmDriver::class, new FakeScmDriverForPostReview(
        checkRunException: new RuntimeException('provider_error'),
    ));

    PostReview::dispatchSync($review->id);

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::Failed)
        ->and($review->failure_reason)->toBe('provider_error')
        ->and($review->summary_comment_id)->not->toBeNull()
        ->and($review->github_check_run_id)->toBeNull()
        ->and($review->finished_at)->not->toBeNull();
});

test('unsafe exception messages are mapped to a generic failure reason', function () {
    $review = postableReviewFixture();
    app()->instance(ScmDriver::class, new FakeScmDriverForPostReview(
        checkRunException: new RuntimeException('content policy rejected: diff --git a/secret.php b/secret.php'),
    ));

    PostReview::dispatchSync($review->id);

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::Failed)
        ->and($review->failure_reason)->toBe('review_failed')
        ->and($review->failure_reason)->not->toContain('secret.php')
        ->and($review->failure_reason)->not->toContain('diff --git');
});

test('uniqueId is the review id so dispatch dedupes per review', function () {
    $job = new PostReview('01POSTUNIQUEID000000000000');

    expect($job->uniqueId())->toBe('01POSTUNIQUEID000000000000')
        ->and($job->reviewId)->toBe('01POSTUNIQUEID000000000000');
});

test('the github-app module does not import review jobs', function () {
    $imports = collect(File::allFiles(base_path('app-modules/github-app')))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->filter(fn (SplFileInfo $file) => str_contains(
            File::get($file->getPathname()),
            'Modules\\Review\\Jobs\\',
        ));

    expect($imports)->toBeEmpty();
});

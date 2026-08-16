<?php

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Modules\GitHubApp\Contracts\ScmDriver;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;
use Modules\Review\Actions\PostGeneratedReview;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\FindingStatus;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Models\Finding;
use Modules\Review\Models\Review;

/**
 * Records SCM writes and returns incrementing ids. Optionally fails one
 * inline path with HTTP 422 so the skip path can be asserted.
 */
class FakeScmDriverForPosting implements ScmDriver
{
    public array $postCommentCalls = [];

    public array $checkRunCalls = [];

    public function __construct(public ?string $unprocessablePath = null) {}

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
        if ($path !== null && $path === $this->unprocessablePath) {
            throw new RequestException(new Response(new PsrResponse(422, [], 'Unprocessable Entity')));
        }

        $this->postCommentCalls[] = compact('installationId', 'repositoryFullName', 'pullRequestNumber', 'body', 'path', 'line', 'commitSha');

        return count($this->postCommentCalls) * 10;
    }

    public function checkRun(int $installationId, string $repositoryFullName, string $headSha, string $name, string $summary): int
    {
        $this->checkRunCalls[] = compact('installationId', 'repositoryFullName', 'headSha', 'name', 'summary');

        return 99;
    }
}

function reviewReadyToPost(array $findings = []): Review
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
    ]);

    foreach ($findings as $attributes) {
        Finding::factory()->create([
            'review_id' => $review->id,
            'status' => FindingStatus::Approved,
            'github_comment_id' => null,
            ...$attributes,
        ]);
    }

    return $review->fresh(['findings', 'pullRequest.repository.installation']);
}

test('it posts a marked summary, eligible inlines, and a kappy-review check run', function () {
    $review = reviewReadyToPost([
        [
            'severity' => FindingSeverity::High,
            'path' => 'app/Widget.php',
            'line' => 10,
            'title' => 'Missing validation',
            'message' => 'Validate the payload.',
            'suggestion' => 'Use a FormRequest.',
            'agent_prompt' => 'secret agent prompt must not leak',
        ],
        [
            'severity' => FindingSeverity::Nit,
            'path' => 'app/Widget.php',
            'line' => 20,
            'title' => 'Rename this variable',
            'message' => 'The name is unclear.',
        ],
    ]);

    $scm = new FakeScmDriverForPosting;
    app()->instance(ScmDriver::class, $scm);

    app(PostGeneratedReview::class)->execute($review);

    $review->refresh();
    $review->load('findings');

    $marker = config('kappy.review.ai_marker');

    expect($review->summary_comment_id)->toBe(10)
        ->and($review->github_check_run_id)->toBe(99)
        ->and($scm->postCommentCalls)->toHaveCount(2)
        ->and($scm->postCommentCalls[0]['path'])->toBeNull()
        ->and($scm->postCommentCalls[0]['body'])->toStartWith($marker)
        ->and($scm->postCommentCalls[0]['body'])->toContain('Adds a widget.')
        ->and($scm->postCommentCalls[0]['body'])->toContain('A controller was added.')
        ->and($scm->postCommentCalls[0]['body'])->toContain('Rename this variable')
        ->and($scm->postCommentCalls[1]['path'])->toBe('app/Widget.php')
        ->and($scm->postCommentCalls[1]['line'])->toBe(10)
        ->and($scm->postCommentCalls[1]['commitSha'])->toBe(str_repeat('c', 40))
        ->and($scm->postCommentCalls[1]['body'])->toStartWith($marker)
        ->and($scm->postCommentCalls[1]['body'])->toContain('Missing validation')
        ->and($scm->postCommentCalls[1]['body'])->toContain('Validate the payload.')
        ->and($scm->postCommentCalls[1]['body'])->toContain('Use a FormRequest.')
        ->and($scm->postCommentCalls[1]['body'])->not->toContain('secret agent prompt must not leak')
        ->and($scm->checkRunCalls)->toHaveCount(1)
        ->and($scm->checkRunCalls[0]['name'])->toBe('kappy-review')
        ->and($scm->checkRunCalls[0]['headSha'])->toBe(str_repeat('c', 40))
        ->and($scm->checkRunCalls[0]['summary'])->toBe('Adds a widget.');

    $posted = $review->findings->firstWhere('title', 'Missing validation');
    $folded = $review->findings->firstWhere('title', 'Rename this variable');

    expect($posted->status)->toBe(FindingStatus::Posted)
        ->and($posted->github_comment_id)->toBe(20)
        ->and($folded->status)->toBe(FindingStatus::Approved)
        ->and($folded->github_comment_id)->toBeNull();
});

test('it still posts the summary and check run when there are no findings', function () {
    $review = reviewReadyToPost();
    $scm = new FakeScmDriverForPosting;
    app()->instance(ScmDriver::class, $scm);

    app(PostGeneratedReview::class)->execute($review);

    $review->refresh();

    expect($review->summary_comment_id)->not->toBeNull()
        ->and($review->github_check_run_id)->toBe(99)
        ->and($scm->postCommentCalls)->toHaveCount(1)
        ->and($scm->postCommentCalls[0]['path'])->toBeNull()
        ->and($scm->checkRunCalls)->toHaveCount(1);
});

test('it skips already stored github ids', function () {
    $review = reviewReadyToPost([
        [
            'severity' => FindingSeverity::High,
            'path' => 'app/Widget.php',
            'line' => 10,
            'title' => 'Already posted',
            'github_comment_id' => 555,
            'status' => FindingStatus::Posted,
        ],
    ]);
    $review->update([
        'summary_comment_id' => 111,
        'github_check_run_id' => 222,
    ]);

    $scm = new FakeScmDriverForPosting;
    app()->instance(ScmDriver::class, $scm);

    app(PostGeneratedReview::class)->execute($review->fresh(['findings', 'pullRequest.repository.installation']));

    expect($scm->postCommentCalls)->toBeEmpty()
        ->and($scm->checkRunCalls)->toBeEmpty()
        ->and($review->fresh()->summary_comment_id)->toBe(111)
        ->and($review->fresh()->github_check_run_id)->toBe(222)
        ->and($review->fresh()->findings->first()->github_comment_id)->toBe(555);
});

test('an inline 422 skips that finding and still posts the rest', function () {
    $review = reviewReadyToPost([
        [
            'severity' => FindingSeverity::High,
            'path' => 'app/Bad.php',
            'line' => 1,
            'title' => 'Unanchorable',
            'message' => 'This line is not in the diff.',
        ],
        [
            'severity' => FindingSeverity::High,
            'path' => 'app/Good.php',
            'line' => 2,
            'title' => 'Still posts',
            'message' => 'This one is fine.',
        ],
    ]);

    $scm = new FakeScmDriverForPosting(unprocessablePath: 'app/Bad.php');
    app()->instance(ScmDriver::class, $scm);

    app(PostGeneratedReview::class)->execute($review);

    $review->refresh()->load('findings');

    expect($review->summary_comment_id)->not->toBeNull()
        ->and($review->github_check_run_id)->toBe(99)
        ->and($review->findings->firstWhere('title', 'Unanchorable')->github_comment_id)->toBeNull()
        ->and($review->findings->firstWhere('title', 'Unanchorable')->status)->toBe(FindingStatus::Approved)
        ->and($review->findings->firstWhere('title', 'Still posts')->status)->toBe(FindingStatus::Posted)
        ->and($review->status)->toBe(ReviewStatus::ReadyToPost)
        ->and($review->failure_reason)->toBeNull();
});

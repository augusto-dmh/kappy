<?php

use Illuminate\Support\Facades\Bus;
use Modules\GitHubApp\Actions\HandlePullRequestEvent;
use Modules\GitHubApp\Enums\PullRequestState;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;
use Modules\Review\Contracts\ReviewDispatcher;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Enums\ReviewTrigger;
use Modules\Review\Models\Review;

/**
 * dispatch() pushes ProcessReview onto the (sync, in tests) queue; faking the
 * bus here keeps these tests about enqueue behaviour only, not job execution.
 * The fake is unfiltered so this module never imports review Jobs.
 */
beforeEach(function () {
    Bus::fake();
});

function prFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__.'/../fixtures/'.$name.'.json'), true);
}

function makeRepo(array $attributes = []): Repository
{
    $account = Account::factory()->create();
    $installation = Installation::factory()->create(['account_id' => $account->id]);

    return Repository::factory()->create([
        'installation_id' => $installation->id,
        'github_repo_id' => 100000001,
        ...$attributes,
    ]);
}

function handlePr(array $payload): void
{
    app(HandlePullRequestEvent::class)->execute($payload);
}

test('pull_request.opened creates a pull request row with state Open', function () {
    makeRepo();

    handlePr(prFixture('pull_request.opened'));

    $pr = PullRequest::first();
    expect($pr)->not->toBeNull()
        ->and($pr->github_pr_number)->toBe(42)
        ->and($pr->title)->toBe('feat: add a new feature')
        ->and($pr->author_login)->toBe('contributor')
        ->and($pr->head_sha)->toBe('11223344112233441122334411223344aabbccdd')
        ->and($pr->state)->toBe(PullRequestState::Open);
});

test('opened then synchronize results in one row with an advanced head_sha', function () {
    makeRepo();

    handlePr(prFixture('pull_request.opened'));
    handlePr(prFixture('pull_request.synchronize'));

    expect(PullRequest::count())->toBe(1);

    $pr = PullRequest::first();
    expect($pr->head_sha)->toBe('99887766998877669988776699887766aabbccdd')
        ->and($pr->state)->toBe(PullRequestState::Open);
});

test('pull_request.closed sets state to Closed', function () {
    makeRepo();

    handlePr(prFixture('pull_request.opened'));
    handlePr(prFixture('pull_request.closed'));

    expect(PullRequest::first()->state)->toBe(PullRequestState::Closed);
});

test('pull_request.closed with merged=true sets state to Merged', function () {
    makeRepo();

    handlePr(prFixture('pull_request.opened'));
    handlePr(prFixture('pull_request.closed_merged'));

    expect(PullRequest::first()->state)->toBe(PullRequestState::Merged);
});

test('pull_request.closed then reopened cycles state back to Open', function () {
    makeRepo();

    handlePr(prFixture('pull_request.opened'));
    handlePr(prFixture('pull_request.closed'));
    handlePr(prFixture('pull_request.reopened'));

    expect(PullRequest::count())->toBe(1)
        ->and(PullRequest::first()->state)->toBe(PullRequestState::Open);
});

test('pull_request event for an unknown repository is safely ignored', function () {
    handlePr(prFixture('pull_request.opened'));

    expect(PullRequest::count())->toBe(0);
});

test('out-of-order synchronize for a non-existent pr creates it with state Open', function () {
    makeRepo();

    handlePr(prFixture('pull_request.synchronize'));

    expect(PullRequest::count())->toBe(1)
        ->and(PullRequest::first()->state)->toBe(PullRequestState::Open)
        ->and(PullRequest::first()->head_sha)->toBe('99887766998877669988776699887766aabbccdd');
});

test('a non-closed action (edited) on a merged pull request preserves the Merged state', function () {
    makeRepo();

    handlePr(prFixture('pull_request.opened'));
    handlePr(prFixture('pull_request.closed_merged'));
    handlePr(prFixture('pull_request.edited'));

    expect(PullRequest::count())->toBe(1)
        ->and(PullRequest::first()->state)->toBe(PullRequestState::Merged);
});

test('opened with review enabled enqueues a queued review for the head sha', function () {
    makeRepo();

    handlePr(prFixture('pull_request.opened'));

    $review = Review::first();
    expect(Review::count())->toBe(1)
        ->and($review->pull_request_id)->toBe(PullRequest::first()->id)
        ->and($review->head_sha)->toBe('11223344112233441122334411223344aabbccdd')
        ->and($review->status)->toBe(ReviewStatus::Queued)
        ->and($review->trigger)->toBe(ReviewTrigger::PrOpened);
});

test('synchronize with review enabled enqueues a queued review for the new head sha', function () {
    makeRepo();

    handlePr(prFixture('pull_request.opened'));
    handlePr(prFixture('pull_request.synchronize'));

    expect(Review::count())->toBe(2)
        ->and(Review::where('head_sha', '99887766998877669988776699887766aabbccdd')->first())
        ->trigger->toBe(ReviewTrigger::PrSynchronize)
        ->status->toBe(ReviewStatus::Queued);
});

test('review_enabled false does not enqueue a review', function () {
    makeRepo(['review_enabled' => false]);

    handlePr(prFixture('pull_request.opened'));
    handlePr(prFixture('pull_request.synchronize'));

    expect(PullRequest::count())->toBe(1)
        ->and(Review::count())->toBe(0);
});

test('duplicate opened delivery for the same head sha is idempotent', function () {
    makeRepo();

    handlePr(prFixture('pull_request.opened'));
    handlePr(prFixture('pull_request.opened'));

    expect(Review::count())->toBe(1);
});

test('non-eligible pull_request actions do not enqueue a review', function () {
    makeRepo();

    handlePr(prFixture('pull_request.opened'));
    expect(Review::count())->toBe(1);

    handlePr(prFixture('pull_request.edited'));
    handlePr(prFixture('pull_request.closed'));
    handlePr(prFixture('pull_request.reopened'));

    expect(Review::count())->toBe(1);
});

test('HandlePullRequestEvent depends on the review dispatcher contract only', function () {
    $parameter = (new ReflectionClass(HandlePullRequestEvent::class))
        ->getConstructor()
        ->getParameters()[0];

    expect($parameter->getType()->getName())->toBe(ReviewDispatcher::class);

    $source = file_get_contents(
        (new ReflectionClass(HandlePullRequestEvent::class))->getFileName()
    );

    expect($source)->not->toContain('Modules\\Review\\Services')
        ->and($source)->not->toContain('Modules\\Review\\Actions')
        ->and($source)->not->toContain('Modules\\Review\\Jobs')
        ->and($source)->not->toContain('Modules\\Review\\Http');
});

<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Bus;
use Modules\GitHubApp\Models\PullRequest;
use Modules\Review\Contracts\ReviewDispatcher;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Enums\ReviewTrigger;
use Modules\Review\Jobs\ProcessReview;
use Modules\Review\Models\Review;
use Modules\Review\Services\EloquentReviewDispatcher;

/**
 * Every dispatch() call pushes ProcessReview onto the (sync, in tests) queue;
 * faking it here keeps these tests about Review row creation only, not job
 * execution, which ProcessReviewTest covers directly.
 */
beforeEach(function () {
    Bus::fake(ProcessReview::class);
});

test('dispatch creates a queued review for a new pull request head sha', function () {
    $pullRequest = PullRequest::factory()->create();
    $headSha = '11223344112233441122334411223344aabbccdd';

    $review = app(ReviewDispatcher::class)->dispatch(
        $pullRequest,
        $headSha,
        ReviewTrigger::PrOpened,
    );

    expect($review->pull_request_id)->toBe($pullRequest->id)
        ->and($review->head_sha)->toBe($headSha)
        ->and($review->status)->toBe(ReviewStatus::Queued)
        ->and($review->trigger)->toBe(ReviewTrigger::PrOpened)
        ->and(Review::count())->toBe(1);
});

test('dispatch recovers when a unique constraint race occurs for the same head sha', function () {
    $pullRequest = PullRequest::factory()->create();
    $headSha = '11223344112233441122334411223344aabbccdd';
    $dispatcher = app(ReviewDispatcher::class);

    $first = $dispatcher->dispatch($pullRequest, $headSha, ReviewTrigger::PrOpened);
    $second = $dispatcher->dispatch($pullRequest, $headSha, ReviewTrigger::PrSynchronize);

    expect(Review::count())->toBe(1)
        ->and($second->is($first))->toBeTrue()
        ->and($second->trigger)->toBe(ReviewTrigger::PrOpened)
        ->and($second->status)->toBe(ReviewStatus::Queued);
});

test('dispatch creates a separate review when the head sha advances', function () {
    $pullRequest = PullRequest::factory()->create();
    $dispatcher = app(ReviewDispatcher::class);

    $dispatcher->dispatch($pullRequest, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ReviewTrigger::PrOpened);
    $dispatcher->dispatch($pullRequest, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ReviewTrigger::PrSynchronize);

    expect(Review::count())->toBe(2);
});

test('review dispatcher is bound to the eloquent implementation', function () {
    expect(app(ReviewDispatcher::class))->toBeInstanceOf(EloquentReviewDispatcher::class);
});

test('dispatch pushes ProcessReview on the reviews queue when it creates a new review', function () {
    $pullRequest = PullRequest::factory()->create();
    $headSha = '11223344112233441122334411223344aabbccdd';

    $review = app(ReviewDispatcher::class)->dispatch($pullRequest, $headSha, ReviewTrigger::PrOpened);

    Bus::assertDispatched(ProcessReview::class, fn (ProcessReview $job) => $job->reviewId === $review->id
        && $job->queue === 'reviews');
});

test('dispatch does not push another ProcessReview when a unique constraint race returns the existing review', function () {
    $pullRequest = PullRequest::factory()->create();
    $headSha = '11223344112233441122334411223344aabbccdd';
    $dispatcher = app(ReviewDispatcher::class);

    $dispatcher->dispatch($pullRequest, $headSha, ReviewTrigger::PrOpened);
    $dispatcher->dispatch($pullRequest, $headSha, ReviewTrigger::PrSynchronize);

    Bus::assertDispatchedTimes(ProcessReview::class, 1);
});

test('the database rejects a duplicate pull request and head sha pair', function () {
    $pullRequest = PullRequest::factory()->create();
    $headSha = '11223344112233441122334411223344aabbccdd';

    Review::factory()->create([
        'pull_request_id' => $pullRequest->id,
        'head_sha' => $headSha,
    ]);

    expect(fn () => Review::factory()->create([
        'pull_request_id' => $pullRequest->id,
        'head_sha' => $headSha,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

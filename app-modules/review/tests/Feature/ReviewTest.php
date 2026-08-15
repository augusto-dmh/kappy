<?php

use Carbon\CarbonInterface;
use Modules\GitHubApp\Models\PullRequest;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Enums\ReviewTrigger;
use Modules\Review\Enums\RiskLevel;
use Modules\Review\Models\Review;

test('the factory creates a review linked to a pull request', function () {
    $review = Review::factory()->create();

    $this->assertModelExists($review);
    expect($review->pullRequest)->toBeInstanceOf(PullRequest::class);
});

test('the trigger and status attributes cast to their enums', function () {
    $review = Review::factory()->create();

    expect($review->trigger)->toBe(ReviewTrigger::PrOpened)
        ->and($review->status)->toBe(ReviewStatus::Queued);
});

test('the started_at and finished_at attributes cast to dates and is_incremental to bool', function () {
    $review = Review::factory()->completed()->create();

    expect($review->is_incremental)->toBeFalse()
        ->and($review->started_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($review->finished_at)->toBeInstanceOf(CarbonInterface::class);
});

test('the id is a 26-character lowercase ulid', function () {
    $review = Review::factory()->create();

    expect($review->id)->toBeString()
        ->and(strlen($review->id))->toBe(26)
        ->and($review->id)->toBe(strtolower($review->id));
});

test('the completed, failed, skipped and ready to post states set the expected status', function () {
    expect(Review::factory()->completed()->create()->status)->toBe(ReviewStatus::Completed)
        ->and(Review::factory()->failed()->create()->status)->toBe(ReviewStatus::Failed)
        ->and(Review::factory()->skipped()->create()->status)->toBe(ReviewStatus::Skipped)
        ->and(Review::factory()->readyToPost()->create()->status)->toBe(ReviewStatus::ReadyToPost);
});

test('the ready to post state persists the summary and telemetry columns', function () {
    $review = Review::factory()->readyToPost()->create();

    expect($review->summary_overview)->not->toBeEmpty()
        ->and($review->summary_walkthrough)->not->toBeEmpty()
        ->and($review->summary_risk_level)->toBe(RiskLevel::Medium)
        ->and($review->generator_model)->toBe('claude-opus-4-8');
});

test('a pull request exposes its reviews', function () {
    $pullRequest = PullRequest::factory()->create();
    Review::factory()->for($pullRequest)->create();

    expect($pullRequest->reviews)->toHaveCount(1)
        ->and($pullRequest->reviews->first())->toBeInstanceOf(Review::class);
});

test('deleting a pull request cascades to its reviews', function () {
    $review = Review::factory()->create();

    $review->pullRequest->delete();

    expect(Review::find($review->id))->toBeNull();
});

<?php

use Modules\Review\Enums\CriticVerdict;
use Modules\Review\Enums\FindingCategory;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\FindingStatus;
use Modules\Review\Models\Finding;
use Modules\Review\Models\Review;

test('the factory creates a finding under a review', function () {
    $finding = Finding::factory()->create();

    $this->assertModelExists($finding);
    expect($finding->review)->toBeInstanceOf(Review::class);
});

test('the category, severity, critic_verdict and status attributes cast to their enums', function () {
    $finding = Finding::factory()->create();

    expect($finding->category)->toBe(FindingCategory::Correctness)
        ->and($finding->severity)->toBe(FindingSeverity::Medium)
        ->and($finding->critic_verdict)->toBe(CriticVerdict::Pending)
        ->and($finding->status)->toBe(FindingStatus::Draft);
});

test('the id is a 26-character lowercase ulid', function () {
    $finding = Finding::factory()->create();

    expect($finding->id)->toBeString()
        ->and(strlen($finding->id))->toBe(26)
        ->and($finding->id)->toBe(strtolower($finding->id));
});

test('the review_id ulid foreign key links the finding to its review', function () {
    $review = Review::factory()->create();
    $finding = Finding::factory()->for($review)->create();

    expect($finding->review_id)->toBe($review->id)
        ->and(strlen($finding->review_id))->toBe(26);
});

test('a review exposes its findings', function () {
    $review = Review::factory()->create();
    Finding::factory()->for($review)->count(2)->create();

    expect($review->findings)->toHaveCount(2)
        ->and($review->findings->first())->toBeInstanceOf(Finding::class);
});

test('deleting a review cascades to its findings', function () {
    $review = Review::factory()->create();
    Finding::factory()->for($review)->create();

    $review->delete();

    expect(Finding::count())->toBe(0);
});

test('the nit and posted states set the expected attributes', function () {
    $nit = Finding::factory()->nit()->create();
    $posted = Finding::factory()->posted()->create();

    expect($nit->severity)->toBe(FindingSeverity::Nit)
        ->and($nit->agent_prompt)->toBeNull()
        ->and($posted->status)->toBe(FindingStatus::Posted)
        ->and($posted->github_comment_id)->not->toBeNull();
});

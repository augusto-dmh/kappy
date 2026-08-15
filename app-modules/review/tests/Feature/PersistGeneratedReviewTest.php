<?php

use Modules\Review\Actions\PersistGeneratedReview;
use Modules\Review\Dto\DraftFinding;
use Modules\Review\Dto\DraftReview;
use Modules\Review\Dto\ReviewSummary;
use Modules\Review\Dto\Telemetry;
use Modules\Review\Enums\FindingCategory;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\FindingStatus;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Enums\RiskLevel;
use Modules\Review\Models\Finding;
use Modules\Review\Models\Review;

function draftFindingFixture(): DraftFinding
{
    return new DraftFinding(
        category: FindingCategory::Security,
        severity: FindingSeverity::High,
        path: 'app/Http/Controllers/WidgetController.php',
        line: 42,
        title: 'Unvalidated request input',
        message: 'The request input is used without validation.',
        suggestion: 'Validate the payload with a FormRequest.',
        agentPrompt: 'In `@app/Http/Controllers/WidgetController.php` around lines 40-44, validate the input.',
        confidence: 80,
    );
}

function draftReviewFixture(array $findings = []): DraftReview
{
    return new DraftReview(
        summary: new ReviewSummary(
            overview: 'Adds a widget endpoint.',
            walkthrough: 'A controller and route were introduced.',
            riskLevel: RiskLevel::Medium,
        ),
        findings: $findings,
        telemetry: new Telemetry(
            model: 'claude-opus-4-8',
            inputTokens: 1200,
            outputTokens: 300,
            cachedTokens: 1000,
            costCents: 42,
            durationMs: 4200,
        ),
    );
}

test('it persists the summary, telemetry, and approved findings then marks the review ready to post', function () {
    $review = Review::factory()->create(['status' => ReviewStatus::Generating]);
    $draft = draftReviewFixture([draftFindingFixture()]);

    app(PersistGeneratedReview::class)->execute($review, $draft);

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::ReadyToPost)
        ->and($review->summary_overview)->toBe('Adds a widget endpoint.')
        ->and($review->summary_walkthrough)->toBe('A controller and route were introduced.')
        ->and($review->summary_risk_level)->toBe(RiskLevel::Medium)
        ->and($review->generator_model)->toBe('claude-opus-4-8')
        ->and($review->input_tokens)->toBe(1200)
        ->and($review->output_tokens)->toBe(300)
        ->and($review->cached_tokens)->toBe(1000)
        ->and($review->cost_cents)->toBe(42);

    expect($review->findings)->toHaveCount(1);

    $finding = $review->findings->first();
    expect($finding->status)->toBe(FindingStatus::Approved)
        ->and($finding->category)->toBe(FindingCategory::Security)
        ->and($finding->severity)->toBe(FindingSeverity::High)
        ->and($finding->path)->toBe('app/Http/Controllers/WidgetController.php')
        ->and($finding->fingerprint)->toBe(hash(
            'sha256',
            'app/Http/Controllers/WidgetController.php'."\n".'The request input is used without validation.'
        ));
});

test('it persists the review as ready to post even when generate returns zero findings', function () {
    $review = Review::factory()->create(['status' => ReviewStatus::Generating]);

    app(PersistGeneratedReview::class)->execute($review, draftReviewFixture([]));

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::ReadyToPost)
        ->and($review->summary_overview)->toBe('Adds a widget endpoint.')
        ->and($review->findings)->toHaveCount(0);
});

test('it never writes a diff-like value onto the review row', function () {
    $review = Review::factory()->create(['status' => ReviewStatus::Generating]);

    app(PersistGeneratedReview::class)->execute($review, draftReviewFixture([draftFindingFixture()]));

    $review->refresh();

    $attributes = $review->getAttributes();

    expect($attributes)->not->toHaveKey('diff')
        ->and(collect($attributes)->filter(fn ($value) => is_string($value) && str_contains($value, 'diff --git')))
        ->toBeEmpty();
});

test('a mid-write finding failure rolls back the review summary update', function () {
    $review = Review::factory()->create([
        'status' => ReviewStatus::Generating,
        'summary_overview' => null,
    ]);

    Finding::creating(function () {
        throw new RuntimeException('forced_finding_write_failure');
    });

    expect(fn () => app(PersistGeneratedReview::class)->execute($review, draftReviewFixture([draftFindingFixture()])))
        ->toThrow(RuntimeException::class, 'forced_finding_write_failure');

    $review->refresh();

    expect($review->status)->toBe(ReviewStatus::Generating)
        ->and($review->summary_overview)->toBeNull()
        ->and($review->findings()->count())->toBe(0);
});

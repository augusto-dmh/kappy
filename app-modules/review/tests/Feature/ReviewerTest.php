<?php

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Modules\Review\Dto\DraftReview;
use Modules\Review\Dto\ReviewSummary;
use Modules\Review\Dto\Telemetry;
use Modules\Review\Enums\FindingCategory;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\RiskLevel;
use Modules\Review\Reviewer\ReviewSchema;
use ValueError;

/**
 * A synthetic structured response whose keys mirror the schema the generate
 * pass emits. The first finding exercises non-null suggestion/agent_prompt;
 * the second exercises both being null. No real customer content.
 *
 * @return array{summary: array<string, string>, findings: list<array<string, mixed>>}
 */
function recordedStructuredResponse(): array
{
    return [
        'summary' => [
            'overview' => 'Adds a widget endpoint.',
            'walkthrough' => 'A new controller and route were introduced.',
            'risk_level' => 'medium',
        ],
        'findings' => [
            [
                'category' => 'security',
                'severity' => 'high',
                'path' => 'app/Http/Controllers/WidgetController.php',
                'line' => 42,
                'title' => 'Unvalidated request input',
                'message' => 'The request input is used without validation.',
                'suggestion' => 'Validate the payload with a FormRequest.',
                'agent_prompt' => 'In `@app/Http/Controllers/WidgetController.php` around lines 40-44, validate the request input before using it.',
                'confidence' => 80,
            ],
            [
                'category' => 'convention',
                'severity' => 'nit',
                'path' => 'app/Http/Controllers/WidgetController.php',
                'line' => 7,
                'title' => 'Import ordering',
                'message' => 'Imports are not alphabetised.',
                'suggestion' => null,
                'agent_prompt' => null,
                'confidence' => 30,
            ],
        ],
    ];
}

test('it maps a recorded structured response to a DraftReview', function () {
    $telemetry = new Telemetry(
        model: 'claude-opus-4-8',
        inputTokens: 1200,
        outputTokens: 300,
        cachedTokens: 1000,
        costCents: null,
        durationMs: 4200,
    );

    $draft = DraftReview::fromStructuredResponse(recordedStructuredResponse(), $telemetry);

    expect($draft->summary->overview)->toBe('Adds a widget endpoint.')
        ->and($draft->summary->walkthrough)->toBe('A new controller and route were introduced.')
        ->and($draft->summary->riskLevel)->toBe(RiskLevel::Medium)
        ->and($draft->telemetry)->toBe($telemetry)
        ->and($draft->findings)->toHaveCount(2);

    $first = $draft->findings[0];

    expect($first->category)->toBe(FindingCategory::Security)
        ->and($first->severity)->toBe(FindingSeverity::High)
        ->and($first->path)->toBe('app/Http/Controllers/WidgetController.php')
        ->and($first->line)->toBe(42)
        ->and($first->title)->toBe('Unvalidated request input')
        ->and($first->suggestion)->toBe('Validate the payload with a FormRequest.')
        ->and($first->agentPrompt)->toContain('@app/Http/Controllers/WidgetController.php')
        ->and($first->confidence)->toBe(80);

    $second = $draft->findings[1];

    expect($second->category)->toBe(FindingCategory::Convention)
        ->and($second->severity)->toBe(FindingSeverity::Nit)
        ->and($second->suggestion)->toBeNull()
        ->and($second->agentPrompt)->toBeNull();
});

test('the findings schema category enum is exactly the four active categories', function () {
    $definition = ReviewSchema::definition(new JsonSchemaTypeFactory);

    $findingProperties = $definition['findings']->toArray()['items']['properties'];

    expect($findingProperties['category']['enum'])
        ->toBe(['correctness', 'security', 'performance', 'convention'])
        ->not->toContain(FindingCategory::Requirement->value)
        ->not->toContain(FindingCategory::Hallucination->value);
});

test('the findings schema severity enum mirrors FindingSeverity', function () {
    $definition = ReviewSchema::definition(new JsonSchemaTypeFactory);

    $findingProperties = $definition['findings']->toArray()['items']['properties'];

    expect($findingProperties['severity']['enum'])
        ->toBe(array_column(FindingSeverity::cases(), 'value'));
});

test('the summary schema risk_level enum mirrors RiskLevel', function () {
    $definition = ReviewSchema::definition(new JsonSchemaTypeFactory);

    $summaryProperties = $definition['summary']->toArray()['properties'];

    expect($summaryProperties['risk_level']['enum'])
        ->toBe(ReviewSchema::RISK_LEVELS)
        ->toBe(array_column(RiskLevel::cases(), 'value'))
        ->not->toContain(FindingSeverity::Nit->value);
});

test('the findings schema allows a null line for file-level findings', function () {
    $definition = ReviewSchema::definition(new JsonSchemaTypeFactory);

    $line = $definition['findings']->toArray()['items']['properties']['line'];

    expect($line['type'])->toBe(['integer', 'null']);
});

test('ReviewSummary rejects an unknown risk_level', function () {
    expect(fn () => ReviewSummary::fromArray([
        'overview' => 'x',
        'walkthrough' => 'y',
        'risk_level' => 'nit',
    ]))->toThrow(ValueError::class);
});

test('the recorded fixture keys match the schema it must satisfy', function () {
    $definition = ReviewSchema::definition(new JsonSchemaTypeFactory);

    $schemaFindingKeys = array_keys($definition['findings']->toArray()['items']['properties']);
    $schemaSummaryKeys = array_keys($definition['summary']->toArray()['properties']);

    $fixture = recordedStructuredResponse();

    sort($schemaFindingKeys);
    sort($schemaSummaryKeys);
    $fixtureFindingKeys = array_keys($fixture['findings'][0]);
    $fixtureSummaryKeys = array_keys($fixture['summary']);
    sort($fixtureFindingKeys);
    sort($fixtureSummaryKeys);

    expect($fixtureFindingKeys)->toBe($schemaFindingKeys)
        ->and($fixtureSummaryKeys)->toBe($schemaSummaryKeys);
});

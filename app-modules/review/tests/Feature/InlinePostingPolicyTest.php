<?php

use Illuminate\Support\Collection;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Models\Finding;
use Modules\Review\Models\Review;
use Modules\Review\Support\InlinePostingPolicy;

/**
 * The policy reads finding attributes only, so unsaved findings are enough —
 * they still carry a real review id to keep the fixtures honest.
 */
function findingsFor(array $overrides): Collection
{
    $review = Review::factory()->readyToPost()->create();

    return collect($overrides)->map(fn (array $attributes) => Finding::factory()->make([
        'review_id' => $review->id,
        ...$attributes,
    ]));
}

test('an anchored finding at or above the floor is posted inline', function () {
    config()->set('kappy.review.inline_min_severity', 'low');

    $split = InlinePostingPolicy::split(findingsFor([
        ['severity' => FindingSeverity::Low, 'path' => 'app/Widget.php', 'line' => 10, 'title' => 'Low anchored'],
    ]));

    expect($split['inline'])->toHaveCount(1)
        ->and($split['inline'][0]->title)->toBe('Low anchored')
        ->and($split['folded'])->toHaveCount(0);
});

test('a nit is folded even when it is anchored and above the floor', function () {
    config()->set('kappy.review.inline_min_severity', 'nit');

    $split = InlinePostingPolicy::split(findingsFor([
        ['severity' => FindingSeverity::Nit, 'path' => 'app/Widget.php', 'line' => 10, 'title' => 'A nit'],
        ['severity' => FindingSeverity::Medium, 'path' => 'app/Widget.php', 'line' => 20, 'title' => 'A medium'],
    ]));

    expect($split['inline'])->toHaveCount(1)
        ->and($split['inline'][0]->title)->toBe('A medium')
        ->and($split['folded'])->toHaveCount(1)
        ->and($split['folded'][0]->title)->toBe('A nit');
});

test('a finding below the configured floor is folded', function () {
    config()->set('kappy.review.inline_min_severity', 'high');

    $split = InlinePostingPolicy::split(findingsFor([
        ['severity' => FindingSeverity::Medium, 'path' => 'app/Widget.php', 'line' => 10, 'title' => 'Below the floor'],
        ['severity' => FindingSeverity::High, 'path' => 'app/Widget.php', 'line' => 20, 'title' => 'At the floor'],
        ['severity' => FindingSeverity::Critical, 'path' => 'app/Widget.php', 'line' => 30, 'title' => 'Above the floor'],
    ]));

    expect($split['inline']->pluck('title')->all())->toBe(['Above the floor', 'At the floor'])
        ->and($split['folded']->pluck('title')->all())->toBe(['Below the floor']);
});

test('a finding with no path or no line is folded', function () {
    config()->set('kappy.review.inline_min_severity', 'low');

    $split = InlinePostingPolicy::split(findingsFor([
        ['severity' => FindingSeverity::High, 'path' => '', 'line' => 10, 'title' => 'No path'],
        ['severity' => FindingSeverity::High, 'path' => null, 'line' => 10, 'title' => 'Null path'],
        ['severity' => FindingSeverity::High, 'path' => 'app/Widget.php', 'line' => null, 'title' => 'No line'],
        ['severity' => FindingSeverity::High, 'path' => 'app/Widget.php', 'line' => 20, 'title' => 'Anchored'],
    ]));

    expect($split['inline']->pluck('title')->all())->toBe(['Anchored'])
        ->and($split['folded']->pluck('title')->all())->toBe(['No path', 'Null path', 'No line']);
});

test('eligible findings are ordered by severity and then by their original order', function () {
    config()->set('kappy.review.inline_min_severity', 'low');

    $split = InlinePostingPolicy::split(findingsFor([
        ['severity' => FindingSeverity::Low, 'path' => 'app/A.php', 'line' => 1, 'title' => 'Low first'],
        ['severity' => FindingSeverity::Critical, 'path' => 'app/B.php', 'line' => 2, 'title' => 'Critical'],
        ['severity' => FindingSeverity::Low, 'path' => 'app/C.php', 'line' => 3, 'title' => 'Low second'],
        ['severity' => FindingSeverity::High, 'path' => 'app/D.php', 'line' => 4, 'title' => 'High'],
    ]));

    expect($split['inline']->pluck('title')->all())->toBe(['Critical', 'High', 'Low first', 'Low second']);
});

test('inline comments are capped at ten by default and the overflow is folded', function () {
    config()->set('kappy.review.inline_min_severity', 'low');

    $split = InlinePostingPolicy::split(findingsFor(
        collect(range(1, 12))->map(fn (int $line) => [
            'severity' => FindingSeverity::High,
            'path' => 'app/Widget.php',
            'line' => $line,
            'title' => "Finding {$line}",
        ])->all()
    ));

    expect(config('kappy.review.max_inline_comments'))->toBe(10)
        ->and($split['inline'])->toHaveCount(10)
        ->and($split['inline']->pluck('line')->all())->toBe(range(1, 10))
        ->and($split['folded']->pluck('line')->all())->toBe([11, 12]);
});

test('the cap is configurable', function () {
    config()->set('kappy.review.inline_min_severity', 'low');
    config()->set('kappy.review.max_inline_comments', 1);

    $split = InlinePostingPolicy::split(findingsFor([
        ['severity' => FindingSeverity::High, 'path' => 'app/A.php', 'line' => 1, 'title' => 'Kept'],
        ['severity' => FindingSeverity::High, 'path' => 'app/B.php', 'line' => 2, 'title' => 'Overflow'],
    ]));

    expect($split['inline']->pluck('title')->all())->toBe(['Kept'])
        ->and($split['folded']->pluck('title')->all())->toBe(['Overflow']);
});

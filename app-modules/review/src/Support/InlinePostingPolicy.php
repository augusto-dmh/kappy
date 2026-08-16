<?php

namespace Modules\Review\Support;

use Illuminate\Support\Collection;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Models\Finding;

/**
 * Splits a review's findings into inline comments versus summary-only
 * (folded) items using the configured severity floor, nit rule, anchor
 * requirement, and inline cap.
 */
final class InlinePostingPolicy
{
    /**
     * @param  Collection<int, Finding>  $findings
     * @return array{inline: Collection<int, Finding>, folded: Collection<int, Finding>}
     */
    public static function split(Collection $findings): array
    {
        $floor = FindingSeverity::from((string) config('kappy.review.inline_min_severity'));
        $cap = (int) config('kappy.review.max_inline_comments');

        $eligible = collect();
        $folded = collect();

        foreach ($findings->values() as $index => $finding) {
            if (self::isEligible($finding, $floor)) {
                $eligible->push(['finding' => $finding, 'index' => $index]);
            } else {
                $folded->push($finding);
            }
        }

        $ranked = $eligible
            ->sort(fn (array $a, array $b) => [$b['finding']->severity->rank(), $a['index']] <=> [$a['finding']->severity->rank(), $b['index']])
            ->values();

        $inline = $ranked->take($cap)->pluck('finding')->values();
        $overflow = $ranked->slice($cap)->pluck('finding')->values();

        return [
            'inline' => $inline,
            'folded' => $folded->concat($overflow)->values(),
        ];
    }

    private static function isEligible(Finding $finding, FindingSeverity $floor): bool
    {
        if ($finding->severity === FindingSeverity::Nit) {
            return false;
        }

        $path = $finding->path;

        if (! is_string($path) || $path === '' || $finding->line === null) {
            return false;
        }

        return $finding->severity->rank() >= $floor->rank();
    }
}

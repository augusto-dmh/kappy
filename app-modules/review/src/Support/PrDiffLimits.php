<?php

namespace Modules\Review\Support;

/**
 * Shared line-count / size gate for PR diffs. Used by the run job (to skip
 * before generate) and by LaravelAiReviewer (defense in depth) so the two
 * cannot drift.
 */
final class PrDiffLimits
{
    public static function lineCount(string $diff): int
    {
        return substr_count($diff, "\n") + ($diff === '' ? 0 : 1);
    }

    public static function maxLines(): int
    {
        return (int) config('kappy.review.max_pr_diff_lines');
    }

    public static function exceedsLimit(string $diff): bool
    {
        return self::lineCount($diff) > self::maxLines();
    }
}

<?php

namespace Modules\Review\Dto;

/**
 * Usage metadata for one review pass. Returned on the DraftReview; persisting
 * it to the Review row is a later concern.
 */
readonly class Telemetry
{
    public function __construct(
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $cachedTokens,
        public ?int $costCents,
        public int $durationMs,
    ) {}
}

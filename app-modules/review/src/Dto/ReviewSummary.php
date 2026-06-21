<?php

namespace Modules\Review\Dto;

/**
 * The PR-level summary the model returns alongside its findings.
 */
readonly class ReviewSummary
{
    public function __construct(
        public string $overview,
        public string $walkthrough,
        public string $riskLevel,
    ) {}

    /**
     * Hydrate from the model's structured response (snake_case keys).
     *
     * @param  array{overview: string, walkthrough: string, risk_level: string}  $summary
     */
    public static function fromArray(array $summary): self
    {
        return new self(
            overview: $summary['overview'],
            walkthrough: $summary['walkthrough'],
            riskLevel: $summary['risk_level'],
        );
    }
}

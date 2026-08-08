<?php

namespace Modules\Review\Dto;

/**
 * The full output of a single generate pass: a summary, the findings, and the
 * usage telemetry. Named "draft" because a later critic pass slots in between
 * this and posting via {@see self::withFindings()} without reshaping it.
 */
readonly class DraftReview
{
    /**
     * @param  list<DraftFinding>  $findings
     */
    public function __construct(
        public ReviewSummary $summary,
        public array $findings,
        public Telemetry $telemetry,
    ) {}

    /**
     * Build from the model's structured response, attaching the telemetry the
     * caller derived from the SDK's usage data.
     *
     * @param  array{summary: array<string, mixed>, findings?: list<array<string, mixed>>}  $response
     */
    public static function fromStructuredResponse(array $response, Telemetry $telemetry): self
    {
        return new self(
            summary: ReviewSummary::fromArray($response['summary']),
            findings: array_map(
                static fn (array $finding): DraftFinding => DraftFinding::fromArray($finding),
                $response['findings'] ?? [],
            ),
            telemetry: $telemetry,
        );
    }

    /**
     * Return a copy carrying a different set of findings (the seam a later
     * critic pass uses to post a filtered subset).
     *
     * @param  list<DraftFinding>  $findings
     */
    public function withFindings(array $findings): self
    {
        return new self($this->summary, $findings, $this->telemetry);
    }
}

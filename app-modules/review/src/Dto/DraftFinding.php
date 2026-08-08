<?php

namespace Modules\Review\Dto;

use Modules\Review\Enums\FindingCategory;
use Modules\Review\Enums\FindingSeverity;

/**
 * A single finding produced by a generate pass, before it is persisted.
 */
readonly class DraftFinding
{
    public function __construct(
        public FindingCategory $category,
        public FindingSeverity $severity,
        public string $path,
        public ?int $line,
        public string $title,
        public string $message,
        public ?string $suggestion,
        public ?string $agentPrompt,
        public int $confidence,
    ) {}

    /**
     * Hydrate from a single finding in the model's structured response,
     * mapping snake_case keys to camelCase and hydrating the enums from their
     * backing values.
     *
     * @param  array{
     *     category: string,
     *     severity: string,
     *     path: string,
     *     line?: int|null,
     *     title: string,
     *     message: string,
     *     suggestion?: string|null,
     *     agent_prompt?: string|null,
     *     confidence: int|string,
     * }  $finding
     */
    public static function fromArray(array $finding): self
    {
        return new self(
            category: FindingCategory::from($finding['category']),
            severity: FindingSeverity::from($finding['severity']),
            path: $finding['path'],
            line: $finding['line'] ?? null,
            title: $finding['title'],
            message: $finding['message'],
            suggestion: $finding['suggestion'] ?? null,
            agentPrompt: $finding['agent_prompt'] ?? null,
            confidence: (int) $finding['confidence'],
        );
    }
}

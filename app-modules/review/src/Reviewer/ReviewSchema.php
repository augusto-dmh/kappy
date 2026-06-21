<?php

namespace Modules\Review\Reviewer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The strict structured-output schema the generate pass must return.
 *
 * The category enum is deliberately the FOUR active categories only. The
 * FindingCategory enum carries two further cases (requirement, hallucination)
 * that later phases emit; they must never appear in the generate schema, so
 * the values are listed explicitly rather than derived from the enum.
 */
class ReviewSchema
{
    /**
     * The categories the generate pass may emit (a strict subset of FindingCategory).
     *
     * @var list<string>
     */
    public const CATEGORIES = ['correctness', 'security', 'performance', 'convention'];

    /**
     * The finding severities, a 1:1 mirror of FindingSeverity's backings.
     *
     * @var list<string>
     */
    public const SEVERITIES = ['critical', 'high', 'medium', 'low', 'nit'];

    /**
     * The allowed summary risk levels (severities plus "none").
     *
     * @var list<string>
     */
    public const RISK_LEVELS = ['critical', 'high', 'medium', 'low', 'none'];

    /**
     * The top-level schema definition the agent returns from schema().
     *
     * @return array<string, Type>
     */
    public static function definition(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->object([
                'overview' => $schema->string()->required(),
                'walkthrough' => $schema->string()->required(),
                'risk_level' => $schema->string()->enum(self::RISK_LEVELS)->required(),
            ])->withoutAdditionalProperties()->required(),

            'findings' => $schema->array()->items(
                $schema->object([
                    'category' => $schema->string()->enum(self::CATEGORIES)->required(),
                    'severity' => $schema->string()->enum(self::SEVERITIES)->required(),
                    'path' => $schema->string()->required(),
                    'line' => $schema->integer()->required(),
                    'title' => $schema->string()->required(),
                    'message' => $schema->string()->required(),
                    'suggestion' => $schema->string()->nullable()->required(),
                    'agent_prompt' => $schema->string()->nullable()->required(),
                    'confidence' => $schema->integer()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }
}

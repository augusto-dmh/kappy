<?php

namespace Modules\Review\Reviewer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The laravel/ai agent that performs a single generate pass. It is wrapped by
 * {@see LaravelAiReviewer} so the rest of the pipeline never depends on the SDK.
 */
class ReviewAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /**
     * The system prompt that governs the review.
     *
     * @throws \RuntimeException When the versioned prompt file is missing or empty.
     */
    public function instructions(): Stringable|string
    {
        $path = __DIR__.'/../../resources/prompts/generate_v1.md';
        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw new \RuntimeException(
                "Review generate prompt is missing or empty at [{$path}]."
            );
        }

        return $contents;
    }

    /**
     * The strict structured-output schema the response must satisfy.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ReviewSchema::definition($schema);
    }

    /**
     * Provider-specific request options. The SDK calls this once per provider
     * and merges the returned array into the request body, so the options are
     * returned flat (not keyed by provider) and only for Anthropic.
     *
     * Prompt caching is a best-effort optimisation, not load-bearing: the exact
     * per-block placement is undocumented, so this only signals intent.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $isAnthropic = $provider === Lab::Anthropic || $provider === Lab::Anthropic->value;

        return $isAnthropic
            ? ['cache_control' => ['type' => 'ephemeral']]
            : [];
    }
}

<?php

namespace Modules\Review\Reviewer;

use Laravel\Ai\Enums\Lab;
use Modules\Review\Contracts\Reviewer;
use Modules\Review\Dto\DraftReview;
use Modules\Review\Dto\ReviewInput;
use Modules\Review\Dto\Telemetry;
use Modules\Review\Support\PrDiffLimits;

/**
 * The laravel/ai implementation of the Reviewer seam. It builds the prompt
 * envelope, runs one structured generate pass, and maps the response onto
 * Kappy's DTOs. The diff stays in memory and is never logged or persisted.
 */
class LaravelAiReviewer implements Reviewer
{
    public function __construct(private ReviewAgent $agent) {}

    public function generate(ReviewInput $input): DraftReview
    {
        $this->assertDiffWithinLimit($input);

        $startedAt = hrtime(true);

        $response = $this->agent->prompt(
            $this->buildEnvelope($input),
            provider: Lab::Anthropic,
            model: config('kappy.review.generator_model'),
            timeout: 120,
        );

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $telemetry = new Telemetry(
            model: config('kappy.review.generator_model'),
            inputTokens: $response->usage->promptTokens,
            outputTokens: $response->usage->completionTokens,
            cachedTokens: $response->usage->cacheReadInputTokens,
            costCents: null,
            durationMs: $durationMs,
        );

        return DraftReview::fromStructuredResponse($response->toArray(), $telemetry);
    }

    /**
     * Build the untrusted-input envelope handed to the model. PR metadata and
     * the diff are XML-escaped so literal angle brackets in customer source
     * cannot forge the delimiters or smuggle instructions; the system prompt
     * teaches that escaped content inside <diff> is source, never envelope.
     *
     * Exposed so the escaping can be asserted without invoking the SDK.
     */
    public function buildEnvelope(ReviewInput $input): string
    {
        $metadata = sprintf(
            "<pr_metadata>\n<title>%s</title>\n<author>%s</author>\n<repository>%s</repository>\n<base_sha>%s</base_sha>\n<head_sha>%s</head_sha>\n</pr_metadata>",
            e($input->title),
            e($input->author),
            e($input->repositoryFullName),
            e($input->baseSha),
            e($input->headSha),
        );

        // [L<N>] line annotation is wired in PR3 (T9); PR2 escapes the raw diff.
        $diff = e($input->diff);

        return $metadata."\n<diff>\n".$diff."\n</diff>";
    }

    /**
     * Skip oversized diffs before envelope allocation or the model call.
     *
     * @throws \RuntimeException When the diff exceeds kappy.review.max_pr_diff_lines.
     */
    private function assertDiffWithinLimit(ReviewInput $input): void
    {
        if (PrDiffLimits::exceedsLimit($input->diff)) {
            $lineCount = PrDiffLimits::lineCount($input->diff);
            $maxLines = PrDiffLimits::maxLines();

            throw new \RuntimeException(
                "Pull request diff has {$lineCount} lines, which exceeds the configured limit of {$maxLines}."
            );
        }
    }
}

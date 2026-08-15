<?php

namespace Modules\Review\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Review\Dto\DraftFinding;
use Modules\Review\Dto\DraftReview;
use Modules\Review\Enums\FindingStatus;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Models\Review;

/**
 * Maps a successful generate pass onto its Review row: the PR-level summary,
 * generator telemetry, and one Finding per draft finding. The critic phase is
 * skipped this cycle, so every finding lands pre-approved. Runs inside a
 * transaction so a mid-write failure never leaves partial Finding rows.
 */
class PersistGeneratedReview
{
    public function execute(Review $review, DraftReview $draft): void
    {
        DB::transaction(function () use ($review, $draft) {
            $review->update([
                'summary_overview' => $draft->summary->overview,
                'summary_walkthrough' => $draft->summary->walkthrough,
                'summary_risk_level' => $draft->summary->riskLevel,
                'generator_model' => $draft->telemetry->model,
                'input_tokens' => $draft->telemetry->inputTokens,
                'output_tokens' => $draft->telemetry->outputTokens,
                'cached_tokens' => $draft->telemetry->cachedTokens,
                'cost_cents' => $draft->telemetry->costCents,
                'status' => ReviewStatus::ReadyToPost,
            ]);

            foreach ($draft->findings as $finding) {
                $review->findings()->create([
                    'category' => $finding->category,
                    'severity' => $finding->severity,
                    'path' => $finding->path,
                    'line' => $finding->line,
                    'title' => $finding->title,
                    'message' => $finding->message,
                    'suggestion' => $finding->suggestion,
                    'agent_prompt' => $finding->agentPrompt,
                    'confidence' => $finding->confidence,
                    'status' => FindingStatus::Approved,
                    'fingerprint' => $this->fingerprint($finding),
                ]);
            }
        });
    }

    /**
     * Matches the FindingFactory convention: sha256 of the path and message,
     * newline-joined, so identical findings across runs collapse to the same
     * fingerprint.
     */
    private function fingerprint(DraftFinding $finding): string
    {
        return hash('sha256', $finding->path."\n".$finding->message);
    }
}

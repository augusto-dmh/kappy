<?php

namespace Modules\Review\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Modules\Review\Actions\PostGeneratedReview;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Models\Review;

/**
 * Posts a ready-to-post Review to the SCM host. A successful run lands the
 * Review at {@see ReviewStatus::Completed}. Hard write failures map to an
 * allow-listed reason and keep any GitHub ids already stored.
 *
 * Exclusivity: {@see ShouldBeUnique} dedupes queue dispatch; the
 * ready_to_post→posting claim also uses {@see lockForUpdate()} so overlapping
 * handle() calls cannot both start a fresh post of the same row. An already
 * posting Review may continue (retry after a crash mid-write).
 */
class PostReview implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Short, developer-authored failure reasons that are safe to persist.
     * Raw exception messages are never stored — they may echo provider bodies.
     *
     * @var list<string>
     */
    private const SAFE_FAILURE_REASONS = [
        'scm_unreachable',
        'provider_timeout',
        'provider_error',
    ];

    public function __construct(public string $reviewId) {}

    /**
     * One in-flight post per Review, regardless of how many times it is
     * (re)dispatched while already queued.
     */
    public function uniqueId(): string
    {
        return $this->reviewId;
    }

    public function handle(PostGeneratedReview $postGeneratedReview): void
    {
        $review = $this->claimPostableReview();

        if ($review === null) {
            return;
        }

        try {
            $postGeneratedReview->execute($review);

            $review->update([
                'status' => ReviewStatus::Completed,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            $review->update([
                'status' => ReviewStatus::Failed,
                'failure_reason' => $this->safeFailureReason($e),
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * Atomically claim a ready-to-post Review, or continue one already posting.
     */
    private function claimPostableReview(): ?Review
    {
        return DB::transaction(function (): ?Review {
            $review = Review::query()
                ->with('pullRequest.repository.installation')
                ->whereKey($this->reviewId)
                ->lockForUpdate()
                ->first();

            if ($review === null) {
                return null;
            }

            if ($review->status === ReviewStatus::Posting) {
                return $review;
            }

            if ($review->status !== ReviewStatus::ReadyToPost) {
                return null;
            }

            $review->update(['status' => ReviewStatus::Posting]);

            return $review->fresh(['findings', 'pullRequest.repository.installation']);
        });
    }

    /**
     * Persist only an allow-listed short reason — never the raw exception
     * message, which may embed provider echoes of customer source.
     */
    private function safeFailureReason(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (in_array($message, self::SAFE_FAILURE_REASONS, true)) {
            return $message;
        }

        return 'review_failed';
    }
}

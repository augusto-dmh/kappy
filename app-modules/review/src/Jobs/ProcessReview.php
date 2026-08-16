<?php

namespace Modules\Review\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Modules\GitHubApp\Contracts\ScmDriver;
use Modules\Review\Actions\PersistGeneratedReview;
use Modules\Review\Contracts\Reviewer;
use Modules\Review\Dto\ReviewInput;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Models\Review;
use Modules\Review\Support\PrDiffLimits;

/**
 * Runs one queued Review through the pipeline: fetch its diff, generate a
 * draft review, and persist it. The critic phase is skipped this cycle, so a
 * successful run lands the Review at {@see ReviewStatus::ReadyToPost}.
 *
 * Exclusivity: {@see ShouldBeUnique} dedupes queue dispatch; the queued→fetching
 * claim also uses {@see lockForUpdate()} so overlapping handle() calls cannot
 * both start work on the same row.
 */
class ProcessReview implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Short, developer-authored failure reasons that are safe to persist.
     * Raw exception messages are never stored — they may echo customer diffs.
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
     * One in-flight run per Review, regardless of how many times it is
     * (re)dispatched while already queued.
     */
    public function uniqueId(): string
    {
        return $this->reviewId;
    }

    public function handle(ScmDriver $scmDriver, Reviewer $reviewer, PersistGeneratedReview $persistGeneratedReview): void
    {
        $review = $this->claimQueuedReview();

        if ($review === null) {
            return;
        }

        $pullRequest = $review->pullRequest;
        $repository = $pullRequest->repository;
        $installation = $repository->installation;

        try {
            $diff = $scmDriver->diff(
                $installation->github_installation_id,
                $repository->full_name,
                $pullRequest->github_pr_number,
            );

            if (PrDiffLimits::exceedsLimit($diff)) {
                $review->update([
                    'status' => ReviewStatus::Skipped,
                    'failure_reason' => 'diff_exceeds_limit',
                    'finished_at' => now(),
                ]);

                return;
            }

            $review->update(['status' => ReviewStatus::Generating]);

            $input = new ReviewInput(
                diff: $diff,
                title: $pullRequest->title,
                author: $pullRequest->author_login,
                baseSha: $pullRequest->base_sha,
                headSha: $review->head_sha,
                repositoryFullName: $repository->full_name,
            );

            $draft = $reviewer->generate($input);

            $persistGeneratedReview->execute($review, $draft);

            PostReview::dispatch($review->id)->onQueue('reviews');
        } catch (\Throwable $e) {
            report($e);

            if ($this->isDiffTooLarge($e)) {
                $review->update([
                    'status' => ReviewStatus::Skipped,
                    'failure_reason' => 'diff_exceeds_limit',
                    'finished_at' => now(),
                ]);

                return;
            }

            $review->update([
                'status' => ReviewStatus::Failed,
                'failure_reason' => $this->safeFailureReason($e),
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * Atomically claim a queued Review for this job. Concurrent claimants
     * see a non-queued row and become a no-op.
     */
    private function claimQueuedReview(): ?Review
    {
        return DB::transaction(function (): ?Review {
            $review = Review::query()
                ->with('pullRequest.repository.installation')
                ->whereKey($this->reviewId)
                ->lockForUpdate()
                ->first();

            if ($review === null || $review->status !== ReviewStatus::Queued) {
                return null;
            }

            $review->update([
                'started_at' => now(),
                'status' => ReviewStatus::Fetching,
            ]);

            return $review->fresh(['pullRequest.repository.installation']);
        });
    }

    /**
     * Detect the oversized-diff RuntimeException authored by LaravelAiReviewer
     * (defense in depth if the job pre-check is bypassed).
     */
    private function isDiffTooLarge(\Throwable $e): bool
    {
        return $e instanceof \RuntimeException
            && str_contains($e->getMessage(), 'exceeds the configured limit');
    }

    /**
     * Persist only an allow-listed short reason — never the raw exception
     * message, which may embed provider/SCM echoes of the customer diff.
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

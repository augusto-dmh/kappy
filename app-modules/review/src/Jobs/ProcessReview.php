<?php

namespace Modules\Review\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\GitHubApp\Contracts\ScmDriver;
use Modules\Review\Actions\PersistGeneratedReview;
use Modules\Review\Contracts\Reviewer;
use Modules\Review\Dto\ReviewInput;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Models\Review;

/**
 * Runs one queued Review through the pipeline: fetch its diff, generate a
 * draft review, and persist it. The critic phase is skipped this cycle, so a
 * successful run lands the Review at {@see ReviewStatus::ReadyToPost}.
 */
class ProcessReview implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

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
        $review = Review::query()
            ->with('pullRequest.repository.installation')
            ->find($this->reviewId);

        if ($review === null || $review->status !== ReviewStatus::Queued) {
            return;
        }

        $review->update(['started_at' => now(), 'status' => ReviewStatus::Fetching]);

        $pullRequest = $review->pullRequest;
        $repository = $pullRequest->repository;
        $installation = $repository->installation;

        $diff = $scmDriver->diff(
            $installation->github_installation_id,
            $repository->full_name,
            $pullRequest->github_pr_number,
        );

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
    }
}

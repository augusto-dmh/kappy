<?php

namespace Modules\Review\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\GitHubApp\Models\PullRequest;
use Modules\Review\Contracts\ReviewDispatcher;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Enums\ReviewTrigger;
use Modules\Review\Jobs\ProcessReview;
use Modules\Review\Models\Review;

class EloquentReviewDispatcher implements ReviewDispatcher
{
    public function dispatch(PullRequest $pullRequest, string $headSha, ReviewTrigger $trigger): Review
    {
        try {
            $review = Review::query()->create([
                'pull_request_id' => $pullRequest->id,
                'head_sha' => $headSha,
                'trigger' => $trigger,
                'status' => ReviewStatus::Queued,
            ]);
        } catch (UniqueConstraintViolationException) {
            return Review::query()
                ->where('pull_request_id', $pullRequest->id)
                ->where('head_sha', $headSha)
                ->firstOrFail();
        }

        // Only the create attempt owns the run — a unique-hit return above
        // means an earlier create already dispatched this Review's job.
        ProcessReview::dispatch($review->id)->onQueue('reviews');

        return $review;
    }
}

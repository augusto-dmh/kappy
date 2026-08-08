<?php

namespace Modules\Review\Contracts;

use Modules\GitHubApp\Models\PullRequest;
use Modules\Review\Enums\ReviewTrigger;
use Modules\Review\Models\Review;

/**
 * Idempotent enqueue seam: create-or-return a Review for a PR head SHA.
 * Callers (e.g. github-app ingest) depend only on this contract.
 */
interface ReviewDispatcher
{
    /**
     * Ensure a Review exists for the given pull request and head SHA.
     * Duplicate calls for the same pair return the existing row unchanged.
     */
    public function dispatch(PullRequest $pullRequest, string $headSha, ReviewTrigger $trigger): Review;
}

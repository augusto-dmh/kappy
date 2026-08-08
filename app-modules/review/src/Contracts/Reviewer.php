<?php

namespace Modules\Review\Contracts;

use Modules\Review\Dto\DraftReview;
use Modules\Review\Dto\ReviewInput;

/**
 * The provider-agnostic seam between Kappy's pipeline and whatever LLM backs
 * it. The pipeline depends only on this contract, never on the SDK, so a
 * provider swap is a single adapter change.
 */
interface Reviewer
{
    /**
     * Run a single review pass over the given input and return the draft.
     */
    public function generate(ReviewInput $input): DraftReview;
}

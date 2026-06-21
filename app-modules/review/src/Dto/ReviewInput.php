<?php

namespace Modules\Review\Dto;

/**
 * The immutable input to one review pass. The diff is held in memory only and
 * is never persisted or logged (privacy invariant).
 */
readonly class ReviewInput
{
    public function __construct(
        public string $diff,
        public string $title,
        public string $author,
        public string $baseSha,
        public string $headSha,
        public string $repositoryFullName,
    ) {}
}

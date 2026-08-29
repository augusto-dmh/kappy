<?php

namespace Modules\Review\Enums;

enum ReviewStatus: string
{
    case Queued = 'queued';
    case Fetching = 'fetching';
    case Generating = 'generating';
    case Critiquing = 'critiquing';
    case ReadyToPost = 'ready_to_post';
    case Posting = 'posting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    /**
     * Collapse pipeline statuses into the four inbox filter groups.
     */
    public function inboxGroup(): string
    {
        return match ($this) {
            self::Completed => 'completed',
            self::Failed => 'failed',
            self::Skipped => 'skipped',
            self::Queued,
            self::Fetching,
            self::Generating,
            self::Critiquing,
            self::ReadyToPost,
            self::Posting => 'in_progress',
        };
    }
}

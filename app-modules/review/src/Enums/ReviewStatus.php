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
}

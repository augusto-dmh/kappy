<?php

namespace Modules\Review\Enums;

enum ReviewStatus: string
{
    case Queued = 'queued';
    case Fetching = 'fetching';
    case Generating = 'generating';
    case Critiquing = 'critiquing';
    case Posting = 'posting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}

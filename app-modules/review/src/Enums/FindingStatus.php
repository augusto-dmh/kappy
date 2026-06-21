<?php

namespace Modules\Review\Enums;

enum FindingStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Posted = 'posted';
    case Resolved = 'resolved';
    case Suppressed = 'suppressed';
}

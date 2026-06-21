<?php

namespace Modules\Review\Enums;

enum CriticVerdict: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

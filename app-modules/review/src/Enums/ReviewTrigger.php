<?php

namespace Modules\Review\Enums;

enum ReviewTrigger: string
{
    case PrOpened = 'pr_opened';
    case PrSynchronize = 'pr_synchronize';
    case Manual = 'manual';
}

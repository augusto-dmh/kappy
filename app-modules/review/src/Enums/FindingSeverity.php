<?php

namespace Modules\Review\Enums;

enum FindingSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Nit = 'nit';
}

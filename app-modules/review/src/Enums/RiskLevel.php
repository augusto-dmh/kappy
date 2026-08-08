<?php

namespace Modules\Review\Enums;

enum RiskLevel: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case None = 'none';
}

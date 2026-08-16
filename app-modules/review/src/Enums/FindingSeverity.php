<?php

namespace Modules\Review\Enums;

enum FindingSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Nit = 'nit';

    /**
     * Higher numbers post first when several findings are eligible for inline.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
            self::Nit => 0,
        };
    }
}

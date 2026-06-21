<?php

namespace Modules\Review\Enums;

enum FindingCategory: string
{
    case Correctness = 'correctness';
    case Security = 'security';
    case Performance = 'performance';
    case Convention = 'convention';
    case Requirement = 'requirement';
    case Hallucination = 'hallucination';
}

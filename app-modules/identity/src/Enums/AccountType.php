<?php

namespace Modules\Identity\Enums;

enum AccountType: string
{
    case Personal = 'personal';
    case Organization = 'organization';
}

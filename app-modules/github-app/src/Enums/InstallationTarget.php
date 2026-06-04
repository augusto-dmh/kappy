<?php

namespace Modules\GitHubApp\Enums;

enum InstallationTarget: string
{
    case User = 'User';
    case Organization = 'Organization';
}

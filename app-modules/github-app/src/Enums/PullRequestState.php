<?php

namespace Modules\GitHubApp\Enums;

enum PullRequestState: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Merged = 'merged';
}

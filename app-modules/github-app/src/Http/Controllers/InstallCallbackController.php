<?php

namespace Modules\GitHubApp\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class InstallCallbackController
{
    public function __invoke(): Response
    {
        return Inertia::render('install/callback');
    }
}

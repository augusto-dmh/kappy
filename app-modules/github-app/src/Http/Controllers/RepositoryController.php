<?php

namespace Modules\GitHubApp\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController
{
    public function index(Request $request): Response
    {
        return Inertia::render('repositories/index', [
            'repositories' => [],
        ]);
    }
}

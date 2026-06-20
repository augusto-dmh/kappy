<?php

namespace Modules\GitHubApp\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;

class RepositoryController
{
    public function index(Request $request): Response
    {
        $repositories = $request->user()
            ->accounts()
            ->with('installations.repositories')
            ->get()
            ->flatMap(fn (Account $account) => $account->installations->flatMap(
                fn ($installation) => $installation->repositories->map(fn (Repository $repo): array => [
                    'id' => $repo->id,
                    'full_name' => $repo->full_name,
                    'private' => $repo->private,
                    'review_enabled' => $repo->review_enabled,
                ])
            ));

        return Inertia::render('repositories/index', [
            'repositories' => $repositories->values(),
        ]);
    }

    public function update(Request $request, Repository $repository): RedirectResponse
    {
        Gate::authorize('update', $repository->installation->account);

        $repository->update(['review_enabled' => $request->boolean('review_enabled')]);

        return back();
    }
}

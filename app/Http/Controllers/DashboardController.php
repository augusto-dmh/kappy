<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Models\Account;

class DashboardController extends Controller
{
    /**
     * Show the authenticated user's account overview.
     */
    public function index(Request $request): Response
    {
        $accounts = $request->user()
            ->accounts()
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'github_login' => $account->github_login,
                'role' => $account->pivot->role->value,
            ]);

        return Inertia::render('dashboard', [
            'accounts' => $accounts,
        ]);
    }
}

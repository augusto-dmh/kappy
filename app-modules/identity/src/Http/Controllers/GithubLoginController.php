<?php

namespace Modules\Identity\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Modules\Identity\Actions\ProvisionUserFromGithub;
use Throwable;

class GithubLoginController
{
    public function __construct(private ProvisionUserFromGithub $provisionUser) {}

    /**
     * Redirect the visitor to GitHub to authorize the application.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['read:user', 'user:email'])
            ->redirect();
    }

    /**
     * Handle the GitHub OAuth callback: provision, then authenticate the user.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error') || $request->missing('code')) {
            return redirect()->route('login')->withErrors([
                'github' => 'GitHub sign-in was cancelled. Please try again.',
            ]);
        }

        try {
            $githubUser = Socialite::driver('github')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors([
                'github' => 'We could not sign you in with GitHub. Please try again.',
            ]);
        }

        $user = $this->provisionUser->execute($githubUser);

        Auth::login($user, remember: true);

        return redirect()->intended(config('fortify.home'));
    }
}

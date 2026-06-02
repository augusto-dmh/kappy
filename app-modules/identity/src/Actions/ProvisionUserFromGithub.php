<?php

namespace Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Modules\Identity\Enums\AccountType;
use Modules\Identity\Enums\MembershipRole;
use Modules\Identity\Models\Account;

class ProvisionUserFromGithub
{
    /**
     * Resolve the local user for an authenticated GitHub identity.
     *
     * Runs in a single transaction so a half-provisioned user can never be
     * left without its account and owner membership. The resolution order is:
     *
     *  1. Returning user — matched by `github_id`; the stored token is refreshed.
     *  2. Link — an existing password user with the same verified email and no
     *     `github_id` adopts the GitHub fields (no duplicate user is created).
     *  3. New user — a fresh User plus a personal Account and an Owner Membership.
     *
     * GitHub emails are verified, so provisioned users are marked verified to
     * pass the `verified` middleware guarding the dashboard.
     */
    public function execute(SocialiteUser $githubUser): User
    {
        return DB::transaction(function () use ($githubUser): User {
            $githubId = $githubUser->getId();
            $email = $githubUser->getEmail();
            $login = $githubUser->getNickname();
            $displayName = $githubUser->getName() ?? $login ?? 'GitHub User';

            $existingByGithubId = User::query()
                ->where('github_id', $githubId)
                ->first();

            if ($existingByGithubId !== null) {
                $existingByGithubId->update([
                    'github_login' => $login,
                    'avatar_url' => $githubUser->getAvatar(),
                    'github_token' => $githubUser->token,
                ]);

                return $existingByGithubId;
            }

            if ($email !== null) {
                $existingByEmail = User::query()
                    ->whereNull('github_id')
                    ->where('email', $email)
                    ->first();

                if ($existingByEmail !== null) {
                    $existingByEmail->fill([
                        'github_id' => $githubId,
                        'github_login' => $login,
                        'avatar_url' => $githubUser->getAvatar(),
                        'github_token' => $githubUser->token,
                    ]);
                    // `email_verified_at` is intentionally not mass-assignable; a
                    // verified GitHub login also verifies a previously unverified user.
                    $existingByEmail->email_verified_at ??= now();
                    $existingByEmail->save();

                    return $existingByEmail;
                }
            }

            $user = new User([
                'name' => $displayName,
                'email' => $email,
                'github_id' => $githubId,
                'github_login' => $login,
                'avatar_url' => $githubUser->getAvatar(),
                'github_token' => $githubUser->token,
            ]);
            // GitHub supplies verified emails, so mark the user verified to clear
            // the `verified` middleware on the dashboard. Set outside mass assignment.
            $user->email_verified_at = now();
            $user->save();

            $account = Account::create([
                'type' => AccountType::Personal,
                'github_account_id' => $githubId,
                // accounts.github_login is non-nullable; fall back deterministically
                // for the (contractually possible) case of a missing GitHub login.
                'github_login' => $login ?? 'github-'.$githubId,
                'name' => $displayName,
            ]);

            $account->members()->attach($user, ['role' => MembershipRole::Owner]);

            return $user;
        });
    }
}

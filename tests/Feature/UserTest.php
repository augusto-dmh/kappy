<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\MembershipRole;
use Modules\Identity\Models\Account;

test('the github token is encrypted at rest and decrypted when accessed', function () {
    $plaintext = 'gho_personalaccesstoken1234567890';

    $user = User::factory()->create(['github_token' => $plaintext]);

    $stored = DB::table('users')->where('id', $user->id)->value('github_token');

    expect($stored)->not->toBeNull()
        ->and($stored)->not->toBe($plaintext)
        ->and($user->fresh()->github_token)->toBe($plaintext);
});

test('a user resolves the accounts they hold a membership on', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();

    $account->members()->attach($user, ['role' => MembershipRole::Owner]);

    expect($user->accounts()->get())->toHaveCount(1)
        ->and($user->accounts->first()->is($account))->toBeTrue()
        ->and($user->accounts->first()->pivot->role)->toBe(MembershipRole::Owner);
});

test('personalAccount returns the personal account, not an organization one', function () {
    $user = User::factory()->create();

    // The organization is attached first (lower id) so the result cannot be
    // satisfied by insertion order alone — only the type filter can pick the
    // personal account.
    $organization = Account::factory()->organization()->create();
    $personal = Account::factory()->create();

    $organization->members()->attach($user, ['role' => MembershipRole::Member]);
    $personal->members()->attach($user, ['role' => MembershipRole::Owner]);

    expect($user->personalAccount())->not->toBeNull()
        ->and($user->personalAccount()->is($personal))->toBeTrue();
});

test('personalAccount returns null when the user has no personal account', function () {
    $user = User::factory()->create();
    $organization = Account::factory()->organization()->create();

    $organization->members()->attach($user, ['role' => MembershipRole::Member]);

    expect($user->personalAccount())->toBeNull();
});

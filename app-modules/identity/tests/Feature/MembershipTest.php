<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Modules\Identity\Enums\MembershipRole;
use Modules\Identity\Models\Account;
use Modules\Identity\Models\Membership;

test('a user can be attached to an account with a role and read back via the pivot', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create();

    $account->members()->attach($user, ['role' => MembershipRole::Owner]);

    $account->refresh();

    expect($account->members)->toHaveCount(1);

    $member = $account->members->first();

    expect($member->is($user))->toBeTrue()
        ->and($member->pivot)->toBeInstanceOf(Membership::class)
        ->and($member->pivot->role)->toBe(MembershipRole::Owner);
});

test('an account exposes its memberships with the role cast to the enum', function () {
    $account = Account::factory()->create();
    $account->members()->attach(User::factory()->create(), ['role' => MembershipRole::Owner]);

    expect($account->memberships)->toHaveCount(1);

    $membership = $account->memberships->first();

    expect($membership)->toBeInstanceOf(Membership::class)
        ->and($membership->role)->toBe(MembershipRole::Owner);
});

test('the unique constraint prevents duplicate user-account memberships', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create();

    Membership::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

    expect(fn () => Membership::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
    ]))->toThrow(QueryException::class);
});

test('the membership factory owner state assigns the owner role', function () {
    $membership = Membership::factory()->owner()->create();

    expect($membership->role)->toBe(MembershipRole::Owner);
});

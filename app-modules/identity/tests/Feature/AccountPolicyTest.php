<?php

use App\Models\User;
use Modules\Identity\Enums\MembershipRole;
use Modules\Identity\Models\Account;

test('a member may view and update their account', function () {
    $account = Account::factory()->create();
    $member = User::factory()->create();
    $account->members()->attach($member, ['role' => MembershipRole::Owner]);

    expect($member->can('view', $account))->toBeTrue()
        ->and($member->can('update', $account))->toBeTrue();
});

test('a non-member may not view or update an account', function () {
    $account = Account::factory()->create();
    $stranger = User::factory()->create();

    expect($stranger->can('view', $account))->toBeFalse()
        ->and($stranger->can('update', $account))->toBeFalse();
});

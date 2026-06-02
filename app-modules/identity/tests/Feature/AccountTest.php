<?php

use Modules\Identity\Enums\AccountType;
use Modules\Identity\Models\Account;

test('the factory creates a persisted account', function () {
    $account = Account::factory()->create();

    $this->assertModelExists($account);
    expect($account->github_account_id)->not->toBeNull()
        ->and($account->github_login)->not->toBeEmpty();
});

test('the type attribute casts to the AccountType enum', function () {
    $account = Account::factory()->create();

    expect($account->type)->toBeInstanceOf(AccountType::class)
        ->and($account->type)->toBe(AccountType::Personal);
});

test('the organization state produces an organization account', function () {
    $account = Account::factory()->organization()->create();

    expect($account->fresh()->type)->toBe(AccountType::Organization);
});

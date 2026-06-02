<?php

use Illuminate\Database\QueryException;
use Modules\GitHubApp\Enums\InstallationTarget;
use Modules\GitHubApp\Enums\RepositorySelection;
use Modules\GitHubApp\Models\Installation;
use Modules\Identity\Models\Account;

test('the factory creates an installation linked to an account', function () {
    $installation = Installation::factory()->create();

    $this->assertModelExists($installation);
    expect($installation->account)->toBeInstanceOf(Account::class);
});

test('the enum attributes cast to their enums', function () {
    $installation = Installation::factory()->create();

    expect($installation->target_type)->toBeInstanceOf(InstallationTarget::class)
        ->and($installation->target_type)->toBe(InstallationTarget::User)
        ->and($installation->repositories_selection)->toBeInstanceOf(RepositorySelection::class)
        ->and($installation->repositories_selection)->toBe(RepositorySelection::All);
});

test('the enum columns store GitHub wire values', function () {
    $installation = Installation::factory()->organization()->create([
        'repositories_selection' => RepositorySelection::Selected,
    ]);

    expect($installation->getRawOriginal('target_type'))->toBe('Organization')
        ->and($installation->getRawOriginal('repositories_selection'))->toBe('selected');
});

test('the organization state casts the target type to Organization', function () {
    $installation = Installation::factory()->organization()->create();

    expect($installation->fresh()->target_type)->toBe(InstallationTarget::Organization);
});

test('the github installation id must be unique', function () {
    $installation = Installation::factory()->create();

    expect(fn () => Installation::factory()->create([
        'github_installation_id' => $installation->github_installation_id,
    ]))->toThrow(QueryException::class);
});

test('an account exposes its installations', function () {
    $account = Account::factory()->create();
    Installation::factory()->for($account)->create();

    expect($account->installations)->toHaveCount(1)
        ->and($account->installations->first())->toBeInstanceOf(Installation::class);
});

<?php

use Modules\GitHubApp\Actions\HandleInstallationEvent;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;

function installationFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__.'/../fixtures/'.$name.'.json'), true);
}

test('installation.created links the installation and repositories to a matching account', function () {
    $account = Account::factory()->create(['github_account_id' => 11111111]);

    (new HandleInstallationEvent)->execute(installationFixture('installation.created'));

    $installation = Installation::where('github_installation_id', 12345678)->first();
    expect($installation)->not->toBeNull()
        ->and($installation->account_id)->toBe($account->id)
        ->and($installation->target_type->value)->toBe('User')
        ->and($installation->repositories_selection->value)->toBe('selected');

    expect(Repository::where('installation_id', $installation->id)->count())->toBe(2);
    expect(Repository::where('github_repo_id', 100000001)->exists())->toBeTrue();
    expect(Repository::where('github_repo_id', 100000002)->exists())->toBeTrue();
});

test('installation.created parks the install when no matching account exists', function () {
    (new HandleInstallationEvent)->execute(installationFixture('installation.created'));

    expect(Installation::count())->toBe(0);
    expect(Repository::count())->toBe(0);
});

test('installation.created parks an org install with no matching org account', function () {
    $payload = installationFixture('installation.created');
    $payload['installation']['account']['id'] = 99999999;
    $payload['installation']['account']['type'] = 'Organization';
    $payload['installation']['target_type'] = 'Organization';

    (new HandleInstallationEvent)->execute($payload);

    expect(Installation::count())->toBe(0);
});

test('installation.deleted removes the installation and cascades repositories', function () {
    $account = Account::factory()->create(['github_account_id' => 11111111]);
    $installation = Installation::factory()->create([
        'account_id' => $account->id,
        'github_installation_id' => 12345678,
    ]);
    Repository::factory()->count(2)->create(['installation_id' => $installation->id]);

    (new HandleInstallationEvent)->execute(installationFixture('installation.deleted'));

    expect(Installation::where('github_installation_id', 12345678)->exists())->toBeFalse();
    expect(Repository::where('installation_id', $installation->id)->count())->toBe(0);
});

test('installation.suspend sets suspended_at on the installation', function () {
    $account = Account::factory()->create(['github_account_id' => 11111111]);
    Installation::factory()->create([
        'account_id' => $account->id,
        'github_installation_id' => 12345678,
        'suspended_at' => null,
    ]);

    (new HandleInstallationEvent)->execute(installationFixture('installation.suspend'));

    $installation = Installation::where('github_installation_id', 12345678)->first();
    expect($installation->suspended_at)->not->toBeNull();
});

test('installation.unsuspend clears suspended_at on the installation', function () {
    $account = Account::factory()->create(['github_account_id' => 11111111]);
    Installation::factory()->create([
        'account_id' => $account->id,
        'github_installation_id' => 12345678,
        'suspended_at' => now(),
    ]);

    (new HandleInstallationEvent)->execute(installationFixture('installation.unsuspend'));

    $installation = Installation::where('github_installation_id', 12345678)->first();
    expect($installation->suspended_at)->toBeNull();
});

test('installation.created is idempotent on re-delivery', function () {
    $account = Account::factory()->create(['github_account_id' => 11111111]);

    (new HandleInstallationEvent)->execute(installationFixture('installation.created'));
    (new HandleInstallationEvent)->execute(installationFixture('installation.created'));

    expect(Installation::where('github_installation_id', 12345678)->count())->toBe(1);
    expect(Repository::count())->toBe(2);
});

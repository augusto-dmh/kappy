<?php

use Modules\GitHubApp\Actions\HandleInstallationRepositoriesEvent;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;

test('installation_repositories.added creates new repository rows for the installation', function () {
    $account = Account::factory()->create(['github_account_id' => 11111111]);
    $installation = Installation::factory()->create([
        'account_id' => $account->id,
        'github_installation_id' => 12345678,
    ]);

    $payload = json_decode(
        file_get_contents(__DIR__.'/../fixtures/installation_repositories.added.json'),
        true
    );

    (new HandleInstallationRepositoriesEvent)->execute($payload);

    $repo = Repository::where('github_repo_id', 100000003)->first();
    expect($repo)->not->toBeNull()
        ->and($repo->installation_id)->toBe($installation->id)
        ->and($repo->full_name)->toBe('testuser/repo-three')
        ->and($repo->private)->toBeFalse();
});

test('installation_repositories.removed deletes the matching repository rows', function () {
    $account = Account::factory()->create(['github_account_id' => 11111111]);
    $installation = Installation::factory()->create([
        'account_id' => $account->id,
        'github_installation_id' => 12345678,
    ]);
    Repository::factory()->create([
        'installation_id' => $installation->id,
        'github_repo_id' => 100000001,
    ]);
    Repository::factory()->create([
        'installation_id' => $installation->id,
        'github_repo_id' => 100000002,
    ]);

    $payload = json_decode(
        file_get_contents(__DIR__.'/../fixtures/installation_repositories.removed.json'),
        true
    );

    (new HandleInstallationRepositoriesEvent)->execute($payload);

    expect(Repository::where('github_repo_id', 100000001)->exists())->toBeFalse();
    expect(Repository::where('github_repo_id', 100000002)->exists())->toBeTrue();
});

test('installation_repositories event is a no-op when the installation is unknown', function () {
    $payload = json_decode(
        file_get_contents(__DIR__.'/../fixtures/installation_repositories.added.json'),
        true
    );

    (new HandleInstallationRepositoriesEvent)->execute($payload);

    expect(Repository::count())->toBe(0);
});

test('installation_repositories.added is idempotent on re-delivery', function () {
    $account = Account::factory()->create(['github_account_id' => 11111111]);
    Installation::factory()->create([
        'account_id' => $account->id,
        'github_installation_id' => 12345678,
    ]);

    $payload = json_decode(
        file_get_contents(__DIR__.'/../fixtures/installation_repositories.added.json'),
        true
    );

    (new HandleInstallationRepositoriesEvent)->execute($payload);
    (new HandleInstallationRepositoriesEvent)->execute($payload);

    expect(Repository::where('github_repo_id', 100000003)->count())->toBe(1);
});

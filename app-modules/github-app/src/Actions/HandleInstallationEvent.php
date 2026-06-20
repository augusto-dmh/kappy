<?php

namespace Modules\GitHubApp\Actions;

use Illuminate\Support\Facades\Log;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;

class HandleInstallationEvent
{
    public function execute(array $payload): void
    {
        match ((string) data_get($payload, 'action')) {
            'created' => $this->handleCreated($payload),
            'deleted' => $this->handleDeleted($payload),
            'suspend' => $this->handleSuspend($payload),
            'unsuspend' => $this->handleUnsuspend($payload),
            default => null,
        };
    }

    private function handleCreated(array $payload): void
    {
        $githubAccountId = (int) data_get($payload, 'installation.account.id');
        $account = Account::where('github_account_id', $githubAccountId)->first();

        if ($account === null) {
            Log::info('GitHub installation parked: no matching account', [
                'github_installation_id' => data_get($payload, 'installation.id'),
                'account_login' => data_get($payload, 'installation.account.login'),
            ]);

            return;
        }

        $installation = Installation::updateOrCreate(
            ['github_installation_id' => data_get($payload, 'installation.id')],
            [
                'account_id' => $account->id,
                'target_type' => data_get($payload, 'installation.target_type'),
                'repositories_selection' => data_get($payload, 'installation.repository_selection'),
            ]
        );

        $this->upsertRepositories($installation, (array) data_get($payload, 'repositories', []));
    }

    private function handleDeleted(array $payload): void
    {
        Installation::where('github_installation_id', data_get($payload, 'installation.id'))->delete();
    }

    private function handleSuspend(array $payload): void
    {
        Installation::where('github_installation_id', data_get($payload, 'installation.id'))
            ->update(['suspended_at' => now()]);
    }

    private function handleUnsuspend(array $payload): void
    {
        Installation::where('github_installation_id', data_get($payload, 'installation.id'))
            ->update(['suspended_at' => null]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $repos
     */
    private function upsertRepositories(Installation $installation, array $repos): void
    {
        foreach ($repos as $repo) {
            Repository::updateOrCreate(
                ['github_repo_id' => data_get($repo, 'id')],
                [
                    'installation_id' => $installation->id,
                    'full_name' => (string) data_get($repo, 'full_name'),
                    'private' => (bool) data_get($repo, 'private', false),
                    'default_branch' => (string) data_get($repo, 'default_branch', 'main'),
                ]
            );
        }
    }
}

<?php

namespace Modules\GitHubApp\Actions;

use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\Repository;

class HandleInstallationRepositoriesEvent
{
    public function execute(array $payload): void
    {
        $installation = Installation::where(
            'github_installation_id',
            data_get($payload, 'installation.id')
        )->first();

        if ($installation === null) {
            return;
        }

        match ((string) data_get($payload, 'action')) {
            'added' => $this->handleAdded($installation, (array) data_get($payload, 'repositories_added', [])),
            'removed' => $this->handleRemoved((array) data_get($payload, 'repositories_removed', [])),
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $repos
     */
    private function handleAdded(Installation $installation, array $repos): void
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

    /**
     * @param  array<int, array<string, mixed>>  $repos
     */
    private function handleRemoved(array $repos): void
    {
        $repoIds = array_column($repos, 'id');

        if ($repoIds === []) {
            return;
        }

        Repository::whereIn('github_repo_id', $repoIds)->delete();
    }
}

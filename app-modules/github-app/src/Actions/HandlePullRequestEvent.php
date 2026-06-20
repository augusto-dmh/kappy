<?php

namespace Modules\GitHubApp\Actions;

use Modules\GitHubApp\Enums\PullRequestState;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;

class HandlePullRequestEvent
{
    public function execute(array $payload): void
    {
        $repository = Repository::where('github_repo_id', data_get($payload, 'repository.id'))->first();

        if ($repository === null) {
            return;
        }

        $action = (string) data_get($payload, 'action');
        $prNumber = (int) data_get($payload, 'pull_request.number');

        $state = match ($action) {
            'closed' => data_get($payload, 'pull_request.merged') === true
                ? PullRequestState::Merged
                : PullRequestState::Closed,
            default => PullRequestState::Open,
        };

        PullRequest::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'github_pr_number' => $prNumber,
            ],
            [
                'title' => (string) data_get($payload, 'pull_request.title'),
                'author_login' => (string) data_get($payload, 'pull_request.user.login'),
                'base_sha' => (string) data_get($payload, 'pull_request.base.sha'),
                'head_sha' => (string) data_get($payload, 'pull_request.head.sha'),
                'state' => $state,
            ]
        );
    }
}

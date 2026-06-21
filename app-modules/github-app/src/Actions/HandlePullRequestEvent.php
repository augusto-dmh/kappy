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

        $prNumber = (int) data_get($payload, 'pull_request.number');

        // Derive state from the PR payload, which is present on every delivery,
        // rather than the webhook action. GitHub fires `pull_request` for ~20
        // actions (edited, labeled, ...); keying off the action would revert an
        // already Closed/Merged PR back to Open on any non-`closed` delivery.
        $state = match (true) {
            data_get($payload, 'pull_request.merged') === true => PullRequestState::Merged,
            data_get($payload, 'pull_request.state') === 'closed' => PullRequestState::Closed,
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

<?php

use Illuminate\Database\QueryException;
use Modules\GitHubApp\Enums\PullRequestState;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;

test('the factory creates a pull request under a repository', function () {
    $pullRequest = PullRequest::factory()->create();

    $this->assertModelExists($pullRequest);
    expect($pullRequest->repository)->toBeInstanceOf(Repository::class);
});

test('the state attribute casts to the PullRequestState enum', function () {
    $pullRequest = PullRequest::factory()->create();

    expect($pullRequest->state)->toBeInstanceOf(PullRequestState::class)
        ->and($pullRequest->state)->toBe(PullRequestState::Open);
});

test('linked_issue_ref is null by default', function () {
    $pullRequest = PullRequest::factory()->create();

    expect($pullRequest->linked_issue_ref)->toBeNull();
});

test('the composite unique blocks a duplicate pr number on the same repository', function () {
    $repository = Repository::factory()->create();
    PullRequest::factory()->for($repository)->create(['github_pr_number' => 42]);

    expect(fn () => PullRequest::factory()->for($repository)->create(['github_pr_number' => 42]))
        ->toThrow(QueryException::class);
});

test('the same pr number is allowed across different repositories', function () {
    PullRequest::factory()->for(Repository::factory())->create(['github_pr_number' => 7]);

    $second = PullRequest::factory()->for(Repository::factory())->create(['github_pr_number' => 7]);

    $this->assertModelExists($second);
});

test('a repository exposes its pull requests', function () {
    $repository = Repository::factory()->create();
    PullRequest::factory()->for($repository)->create();

    expect($repository->pullRequests)->toHaveCount(1)
        ->and($repository->pullRequests->first())->toBeInstanceOf(PullRequest::class);
});

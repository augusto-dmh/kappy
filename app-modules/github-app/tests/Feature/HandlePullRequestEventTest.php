<?php

use Modules\GitHubApp\Actions\HandlePullRequestEvent;
use Modules\GitHubApp\Enums\PullRequestState;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;

function prFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__.'/../fixtures/'.$name.'.json'), true);
}

function makeRepo(): Repository
{
    $account = Account::factory()->create();
    $installation = Installation::factory()->create(['account_id' => $account->id]);

    return Repository::factory()->create([
        'installation_id' => $installation->id,
        'github_repo_id' => 100000001,
    ]);
}

test('pull_request.opened creates a pull request row with state Open', function () {
    makeRepo();

    (new HandlePullRequestEvent)->execute(prFixture('pull_request.opened'));

    $pr = PullRequest::first();
    expect($pr)->not->toBeNull()
        ->and($pr->github_pr_number)->toBe(42)
        ->and($pr->title)->toBe('feat: add a new feature')
        ->and($pr->author_login)->toBe('contributor')
        ->and($pr->head_sha)->toBe('11223344112233441122334411223344aabbccdd')
        ->and($pr->state)->toBe(PullRequestState::Open);
});

test('opened then synchronize results in one row with an advanced head_sha', function () {
    makeRepo();

    (new HandlePullRequestEvent)->execute(prFixture('pull_request.opened'));
    (new HandlePullRequestEvent)->execute(prFixture('pull_request.synchronize'));

    expect(PullRequest::count())->toBe(1);

    $pr = PullRequest::first();
    expect($pr->head_sha)->toBe('99887766998877669988776699887766aabbccdd')
        ->and($pr->state)->toBe(PullRequestState::Open);
});

test('pull_request.closed sets state to Closed', function () {
    makeRepo();

    (new HandlePullRequestEvent)->execute(prFixture('pull_request.opened'));
    (new HandlePullRequestEvent)->execute(prFixture('pull_request.closed'));

    expect(PullRequest::first()->state)->toBe(PullRequestState::Closed);
});

test('pull_request.closed with merged=true sets state to Merged', function () {
    makeRepo();

    (new HandlePullRequestEvent)->execute(prFixture('pull_request.opened'));
    (new HandlePullRequestEvent)->execute(prFixture('pull_request.closed_merged'));

    expect(PullRequest::first()->state)->toBe(PullRequestState::Merged);
});

test('pull_request.closed then reopened cycles state back to Open', function () {
    makeRepo();

    (new HandlePullRequestEvent)->execute(prFixture('pull_request.opened'));
    (new HandlePullRequestEvent)->execute(prFixture('pull_request.closed'));
    (new HandlePullRequestEvent)->execute(prFixture('pull_request.reopened'));

    expect(PullRequest::count())->toBe(1)
        ->and(PullRequest::first()->state)->toBe(PullRequestState::Open);
});

test('pull_request event for an unknown repository is safely ignored', function () {
    (new HandlePullRequestEvent)->execute(prFixture('pull_request.opened'));

    expect(PullRequest::count())->toBe(0);
});

test('out-of-order synchronize for a non-existent pr creates it with state Open', function () {
    makeRepo();

    (new HandlePullRequestEvent)->execute(prFixture('pull_request.synchronize'));

    expect(PullRequest::count())->toBe(1)
        ->and(PullRequest::first()->state)->toBe(PullRequestState::Open)
        ->and(PullRequest::first()->head_sha)->toBe('99887766998877669988776699887766aabbccdd');
});

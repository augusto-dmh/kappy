<?php

use Modules\GitHubApp\Jobs\ProcessGithubWebhook;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;
use Modules\GitHubApp\Models\WebhookEvent;
use Modules\Identity\Models\Account;

function routingFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__.'/../fixtures/'.$name.'.json'), true);
}

test('the job routes a pull_request delivery to its handler and marks it processed', function () {
    $installation = Installation::factory()->create();
    Repository::factory()->create([
        'installation_id' => $installation->id,
        'github_repo_id' => 100000001,
    ]);
    WebhookEvent::factory()->create([
        'github_delivery_id' => 'delivery-pr-1',
        'event' => 'pull_request',
        'processed_at' => null,
    ]);

    ProcessGithubWebhook::dispatchSync('pull_request', 'delivery-pr-1', routingFixture('pull_request.opened'));

    expect(PullRequest::where('github_pr_number', 42)->exists())->toBeTrue()
        ->and(WebhookEvent::where('github_delivery_id', 'delivery-pr-1')->first()->processed_at)->not->toBeNull();
});

test('the job routes an installation delivery to its handler and marks it processed', function () {
    Account::factory()->create(['github_account_id' => 11111111]);
    WebhookEvent::factory()->create([
        'github_delivery_id' => 'delivery-inst-1',
        'event' => 'installation',
        'processed_at' => null,
    ]);

    ProcessGithubWebhook::dispatchSync('installation', 'delivery-inst-1', routingFixture('installation.created'));

    expect(Installation::where('github_installation_id', 12345678)->exists())->toBeTrue()
        ->and(WebhookEvent::where('github_delivery_id', 'delivery-inst-1')->first()->processed_at)->not->toBeNull();
});

test('the job marks an unknown event type processed without routing to a handler', function () {
    WebhookEvent::factory()->create([
        'github_delivery_id' => 'delivery-unknown-1',
        'event' => 'ping',
        'processed_at' => null,
    ]);

    ProcessGithubWebhook::dispatchSync('ping', 'delivery-unknown-1', ['zen' => 'Keep it simple.']);

    expect(WebhookEvent::where('github_delivery_id', 'delivery-unknown-1')->first()->processed_at)->not->toBeNull()
        ->and(PullRequest::count())->toBe(0)
        ->and(Installation::where('github_installation_id', 12345678)->exists())->toBeFalse();
});

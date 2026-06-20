<?php

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Modules\GitHubApp\Jobs\ProcessGithubWebhook;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;
use Modules\GitHubApp\Models\WebhookEvent;

beforeEach(function () {
    config()->set('services.github-app.webhook_secret', 'testsecret');
    Queue::fake();
});

/**
 * POST a raw body to the webhook endpoint, signing it with the given secret.
 */
function postWebhook(string $payload, array $headers = [], ?string $secret = 'testsecret')
{
    if ($secret !== null && ! isset($headers['X-Hub-Signature-256'])) {
        $headers['X-Hub-Signature-256'] = 'sha256='.hash_hmac('sha256', $payload, $secret);
    }

    $server = ['CONTENT_TYPE' => 'application/json'];

    foreach ($headers as $key => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
    }

    return test()->call('POST', '/webhooks/github', server: $server, content: $payload);
}

// --- T9: signature verification ---

test('a delivery with no signature is rejected and persists nothing', function () {
    postWebhook('{"action":"created"}', secret: null)
        ->assertUnauthorized();

    expect(WebhookEvent::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a delivery with an invalid signature is rejected and persists nothing', function () {
    postWebhook('{"action":"created"}', ['X-Hub-Signature-256' => 'sha256=deadbeef'])
        ->assertUnauthorized();

    expect(WebhookEvent::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a correctly signed delivery is not rejected', function () {
    postWebhook('{"action":"created"}', [
        'X-GitHub-Event' => 'ping',
        'X-GitHub-Delivery' => 'd-1',
    ])->assertSuccessful();
});

test('a delivery is rejected when the webhook secret is not configured', function (mixed $secret) {
    config()->set('services.github-app.webhook_secret', $secret);

    // A signature forged against the empty secret must still be rejected — the
    // verifier must never compute the HMAC with an empty key (fail closed).
    postWebhook('{"action":"created"}', [
        'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', '{"action":"created"}', ''),
    ])->assertUnauthorized();

    expect(WebhookEvent::count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'empty string' => [''],
    'null' => [null],
]);

test('the webhook route is rate limited', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($route) => $route->getName() === 'webhooks.github');

    expect($route)->not->toBeNull()
        ->and(collect($route->gatherMiddleware())->contains(fn ($m) => str_contains((string) $m, 'throttle')))
        ->toBeTrue();
});

// --- T10: persist, dedupe, enqueue ---

test('a verified delivery persists one event, enqueues the job, and acks 202', function () {
    postWebhook('{"action":"created","installation":{"id":555}}', [
        'X-GitHub-Event' => 'installation',
        'X-GitHub-Delivery' => 'd-100',
    ])->assertAccepted();

    expect(WebhookEvent::count())->toBe(1);

    $event = WebhookEvent::first();
    expect($event->github_delivery_id)->toBe('d-100')
        ->and($event->event)->toBe('installation')
        ->and($event->action)->toBe('created');

    Queue::assertPushedOn('webhooks', ProcessGithubWebhook::class);
    Queue::assertPushed(ProcessGithubWebhook::class, 1);
});

test('a re-delivered delivery id is an idempotent no-op', function () {
    $body = '{"action":"created","installation":{"id":555}}';
    $headers = ['X-GitHub-Event' => 'installation', 'X-GitHub-Delivery' => 'd-dupe'];

    postWebhook($body, $headers)->assertAccepted();
    postWebhook($body, $headers)->assertAccepted();

    expect(WebhookEvent::count())->toBe(1);
    Queue::assertPushed(ProcessGithubWebhook::class, 1);
});

test('the controller defers all handler work to the queue (fast path)', function () {
    postWebhook('{"action":"opened","installation":{"id":555}}', [
        'X-GitHub-Event' => 'pull_request',
        'X-GitHub-Delivery' => 'd-fast',
    ])->assertAccepted();

    // Only the single audit row is written on-request; no ingestion happens here.
    expect(WebhookEvent::count())->toBe(1)
        ->and(Installation::count())->toBe(0)
        ->and(Repository::count())->toBe(0)
        ->and(PullRequest::count())->toBe(0);

    Queue::assertPushed(ProcessGithubWebhook::class, 1);
});

test('the event resolves to a known installation when present', function () {
    $installation = Installation::factory()->create(['github_installation_id' => 12345]);

    postWebhook('{"action":"created","installation":{"id":12345}}', [
        'X-GitHub-Event' => 'installation',
        'X-GitHub-Delivery' => 'd-known',
    ])->assertAccepted();

    expect(WebhookEvent::first()->installation_id)->toBe($installation->id);
});

test('the event installation id is null when the installation is unknown', function () {
    postWebhook('{"action":"created","installation":{"id":99999}}', [
        'X-GitHub-Event' => 'installation',
        'X-GitHub-Delivery' => 'd-unknown',
    ])->assertAccepted();

    expect(WebhookEvent::first()->installation_id)->toBeNull();
});

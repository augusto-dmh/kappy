<?php

use Modules\GitHubApp\Models\WebhookEvent;

beforeEach(function () {
    config()->set('services.github-app.webhook_secret', 'testsecret');
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

test('a delivery with no signature is rejected and persists nothing', function () {
    postWebhook('{"action":"created"}', secret: null)
        ->assertUnauthorized();

    expect(WebhookEvent::count())->toBe(0);
});

test('a delivery with an invalid signature is rejected and persists nothing', function () {
    postWebhook('{"action":"created"}', ['X-Hub-Signature-256' => 'sha256=deadbeef'])
        ->assertUnauthorized();

    expect(WebhookEvent::count())->toBe(0);
});

test('a correctly signed delivery is not rejected', function () {
    postWebhook('{"action":"created"}', [
        'X-GitHub-Event' => 'ping',
        'X-GitHub-Delivery' => 'd-1',
    ])->assertSuccessful();
});

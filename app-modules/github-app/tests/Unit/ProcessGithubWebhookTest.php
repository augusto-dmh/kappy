<?php

use Modules\GitHubApp\Jobs\ProcessGithubWebhook;

test('uniqueId is the delivery id so dispatch dedupes per delivery', function () {
    $job = new ProcessGithubWebhook('pull_request', 'd-x', ['key' => 'value']);

    expect($job->uniqueId())->toBe('d-x')
        ->and($job->event)->toBe('pull_request')
        ->and($job->deliveryId)->toBe('d-x')
        ->and($job->payload)->toBe(['key' => 'value']);
});

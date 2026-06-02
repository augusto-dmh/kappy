<?php

use Illuminate\Database\QueryException;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\WebhookEvent;

test('the factory creates an unprocessed webhook event', function () {
    $event = WebhookEvent::factory()->create();

    $this->assertModelExists($event);
    expect($event->processed_at)->toBeNull();
});

test('the github delivery id must be unique', function () {
    $event = WebhookEvent::factory()->create();

    expect(fn () => WebhookEvent::factory()->create([
        'github_delivery_id' => $event->github_delivery_id,
    ]))->toThrow(QueryException::class);
});

test('deleting the installation nulls the foreign key but keeps the event as audit', function () {
    $installation = Installation::factory()->create();
    $event = WebhookEvent::factory()->for($installation)->create();

    expect($installation->webhookEvents)->toHaveCount(1)
        ->and($installation->webhookEvents->first()->is($event))->toBeTrue();

    $installation->delete();
    $event->refresh();

    expect($event->exists)->toBeTrue()
        ->and($event->installation_id)->toBeNull();
});

test('a webhook event can exist without an installation', function () {
    $event = WebhookEvent::factory()->unattached()->create();

    expect($event->installation_id)->toBeNull()
        ->and($event->installation)->toBeNull();
});

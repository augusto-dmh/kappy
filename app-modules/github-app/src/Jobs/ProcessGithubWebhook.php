<?php

namespace Modules\GitHubApp\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessGithubWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $event,
        public string $deliveryId,
        public array $payload,
    ) {}

    /**
     * Best-effort dispatch dedupe; the `webhook_events` unique constraint is the
     * authoritative idempotency guarantee (this lock is held until the job finishes).
     */
    public function uniqueId(): string
    {
        return $this->deliveryId;
    }

    /**
     * Route the delivery to its event handler.
     *
     * Stub for this phase — installation/repository/pull-request handlers land in PR4 (T11–T12).
     */
    public function handle(): void
    {
        //
    }
}

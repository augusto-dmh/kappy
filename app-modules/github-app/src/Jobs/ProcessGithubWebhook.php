<?php

namespace Modules\GitHubApp\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\GitHubApp\Actions\HandleInstallationEvent;
use Modules\GitHubApp\Actions\HandleInstallationRepositoriesEvent;
use Modules\GitHubApp\Models\WebhookEvent;

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
     */
    public function handle(
        HandleInstallationEvent $installationHandler,
        HandleInstallationRepositoriesEvent $installationReposHandler,
    ): void {
        match ($this->event) {
            'installation' => $installationHandler->execute($this->payload),
            'installation_repositories' => $installationReposHandler->execute($this->payload),
            default => null,
        };

        WebhookEvent::where('github_delivery_id', $this->deliveryId)
            ->update(['processed_at' => now()]);
    }
}

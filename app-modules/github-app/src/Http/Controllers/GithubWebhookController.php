<?php

namespace Modules\GitHubApp\Http\Controllers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\GitHubApp\Jobs\ProcessGithubWebhook;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\WebhookEvent;
use Symfony\Component\HttpFoundation\Response;

class GithubWebhookController
{
    /**
     * Receive a GitHub webhook delivery: verify, persist idempotently, enqueue, ack fast.
     */
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->hasValidSignature($request), Response::HTTP_UNAUTHORIZED);

        $payload = (array) json_decode($request->getContent(), true);
        $event = (string) $request->header('X-GitHub-Event');
        $deliveryId = (string) $request->header('X-GitHub-Delivery');

        try {
            WebhookEvent::create([
                'installation_id' => $this->resolveInstallationId($payload),
                'github_delivery_id' => $deliveryId,
                'event' => $event,
                'action' => (string) data_get($payload, 'action', ''),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already-seen delivery: idempotent no-op — no second row, no re-dispatch.
            return response()->json(status: Response::HTTP_ACCEPTED);
        }

        ProcessGithubWebhook::dispatch($event, $deliveryId, $payload)->onQueue('webhooks');

        return response()->json(status: Response::HTTP_ACCEPTED);
    }

    /**
     * Map the payload's GitHub installation id to a local installation, if known.
     */
    private function resolveInstallationId(array $payload): ?int
    {
        $githubInstallationId = data_get($payload, 'installation.id');

        if ($githubInstallationId === null) {
            return null;
        }

        return Installation::query()
            ->where('github_installation_id', $githubInstallationId)
            ->value('id');
    }

    /**
     * Verify the `X-Hub-Signature-256` HMAC over the unparsed request body.
     */
    private function hasValidSignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! is_string($signature)) {
            return false;
        }

        $secret = (string) config('services.github-app.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}

<?php

namespace Modules\GitHubApp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GithubWebhookController
{
    /**
     * Receive a GitHub webhook delivery once its signature is verified.
     */
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->hasValidSignature($request), Response::HTTP_UNAUTHORIZED);

        return response()->json(status: Response::HTTP_ACCEPTED);
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

        $expected = 'sha256='.hash_hmac(
            'sha256',
            $request->getContent(),
            (string) config('services.github-app.webhook_secret'),
        );

        return hash_equals($expected, $signature);
    }
}

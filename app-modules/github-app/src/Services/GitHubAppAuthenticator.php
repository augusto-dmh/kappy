<?php

namespace Modules\GitHubApp\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

class GitHubAppAuthenticator
{
    /**
     * Exchange the App's RS256 JWT for a short-lived installation access token.
     *
     * The token is installation-scoped and expires after about an hour. It is
     * returned to the caller and never persisted, cached, or logged — the same
     * goes for the App private key, which only ever lives in configuration.
     * Token shape must not be assumed (GitHub is rolling out `ghs_…` tokens).
     */
    public function installationToken(int $installationId): string
    {
        $response = Http::withToken($this->appJwt())
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->timeout(10)
            ->connectTimeout(3)
            ->post("https://api.github.com/app/installations/{$installationId}/access_tokens")
            ->throw();

        return $response->json('token');
    }

    /**
     * Build the App JWT GitHub requires to mint installation tokens.
     *
     * Claims follow GitHub's App authentication contract: `iss` is the App id,
     * `iat` is backdated 60 seconds to absorb clock drift, and `exp` stays
     * under the 10-minute maximum (9 minutes here).
     */
    private function appJwt(): string
    {
        $issuedAt = time();

        return JWT::encode([
            'iss' => (string) config('services.github-app.app_id'),
            'iat' => $issuedAt - 60,
            'exp' => $issuedAt + 540,
        ], $this->privateKey(), 'RS256');
    }

    /**
     * Resolve the App private key from configuration.
     *
     * `GITHUB_APP_PRIVATE_KEY` may hold either the PEM contents or a path to a
     * PEM file; the key is never stored in the database.
     */
    private function privateKey(): string
    {
        $configured = (string) config('services.github-app.private_key');

        if ($configured !== '' && is_file($configured)) {
            return file_get_contents($configured);
        }

        return $configured;
    }
}

<?php

namespace Modules\GitHubApp\Scm;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;
use Modules\GitHubApp\Contracts\ScmDriver;
use Modules\GitHubApp\Services\GitHubAppAuthenticator;

/**
 * Read-only GitHub implementation of the ScmDriver seam.
 *
 * Every call authenticates with a freshly minted installation token. The
 * write methods are declared by the contract but not implemented yet — the
 * review pipeline that posts findings is what will exercise them.
 */
class GitHubScmDriver implements ScmDriver
{
    public function __construct(public GitHubAppAuthenticator $authenticator) {}

    public function pullRequest(int $installationId, string $repositoryFullName, int $pullRequestNumber): array
    {
        return $this->request($installationId, 'application/vnd.github+json')
            ->get("https://api.github.com/repos/{$repositoryFullName}/pulls/{$pullRequestNumber}")
            ->throw()
            ->json();
    }

    public function diff(int $installationId, string $repositoryFullName, int $pullRequestNumber): string
    {
        // The diff body is returned to the caller only — never persisted to
        // the database and never logged (privacy invariant).
        return $this->request($installationId, 'application/vnd.github.diff')
            ->get("https://api.github.com/repos/{$repositoryFullName}/pulls/{$pullRequestNumber}")
            ->throw()
            ->body();
    }

    public function comments(int $installationId, string $repositoryFullName, int $pullRequestNumber): array
    {
        return $this->request($installationId, 'application/vnd.github+json')
            ->get("https://api.github.com/repos/{$repositoryFullName}/pulls/{$pullRequestNumber}/comments")
            ->throw()
            ->json();
    }

    public function postComment(int $installationId, string $repositoryFullName, int $pullRequestNumber, string $body, ?string $path = null, ?int $line = null): void
    {
        throw new LogicException('posting lands in the review pipeline (Phase 3)');
    }

    public function checkRun(int $installationId, string $repositoryFullName, string $headSha, string $name, string $summary): void
    {
        throw new LogicException('posting lands in the review pipeline (Phase 3)');
    }

    /**
     * Build an installation-authenticated request for the GitHub REST API.
     */
    private function request(int $installationId, string $accept): PendingRequest
    {
        return Http::withToken($this->authenticator->installationToken($installationId))
            ->withHeaders(['Accept' => $accept])
            ->timeout(10)
            ->connectTimeout(3);
    }
}

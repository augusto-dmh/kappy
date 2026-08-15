<?php

namespace Modules\GitHubApp\Scm;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\GitHubApp\Contracts\ScmDriver;
use Modules\GitHubApp\Services\GitHubAppAuthenticator;

/**
 * GitHub implementation of the ScmDriver seam.
 *
 * Every call authenticates with a freshly minted installation token. Comment
 * bodies arrive fully composed: the driver adds no Kappy formatting of its
 * own, it only maps a call onto the right REST resource.
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

    public function postComment(int $installationId, string $repositoryFullName, int $pullRequestNumber, string $body, ?string $path = null, ?int $line = null, ?string $commitSha = null): int
    {
        $isInline = $path !== null || $line !== null;

        if ($isInline && $commitSha === null) {
            throw new InvalidArgumentException('an inline comment must be anchored to a commit sha');
        }

        // A PR-level comment is an issue comment; an anchored one is a review
        // comment on a different endpoint with a different payload.
        $url = $isInline
            ? "https://api.github.com/repos/{$repositoryFullName}/pulls/{$pullRequestNumber}/comments"
            : "https://api.github.com/repos/{$repositoryFullName}/issues/{$pullRequestNumber}/comments";

        $payload = $isInline
            ? ['body' => $body, 'commit_id' => $commitSha, 'path' => $path, 'line' => $line, 'side' => 'RIGHT']
            : ['body' => $body];

        return (int) $this->request($installationId, 'application/vnd.github+json')
            ->post($url, $payload)
            ->throw()
            ->json('id');
    }

    public function checkRun(int $installationId, string $repositoryFullName, string $headSha, string $name, string $summary): int
    {
        return (int) $this->request($installationId, 'application/vnd.github+json')
            ->post("https://api.github.com/repos/{$repositoryFullName}/check-runs", [
                'name' => $name,
                'head_sha' => $headSha,
                'status' => 'completed',
                'conclusion' => 'neutral',
                'output' => ['title' => $name, 'summary' => $summary],
            ])
            ->throw()
            ->json('id');
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

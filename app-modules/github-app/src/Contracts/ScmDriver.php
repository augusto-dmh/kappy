<?php

namespace Modules\GitHubApp\Contracts;

/**
 * Provider-agnostic seam for reading from and writing to a source-control host.
 *
 * Signatures carry no provider-specific types so GitLab/Bitbucket drivers can
 * implement the same contract later: an installation is addressed by Kappy's
 * provider installation id, a repository by its `owner/name` full name, and a
 * pull request by its number on that repository.
 */
interface ScmDriver
{
    /**
     * Fetch a pull request's metadata.
     *
     * @return array<string, mixed> The provider's pull-request representation.
     */
    public function pullRequest(int $installationId, string $repositoryFullName, int $pullRequestNumber): array;

    /**
     * Fetch a pull request's unified diff.
     *
     * The returned diff is customer source: callers must keep it in memory or
     * an ephemeral working directory only — never persist or log it.
     */
    public function diff(int $installationId, string $repositoryFullName, int $pullRequestNumber): string;

    /**
     * Fetch the review comments on a pull request.
     *
     * @return list<array<string, mixed>> The provider's comment representations.
     */
    public function comments(int $installationId, string $repositoryFullName, int $pullRequestNumber): array;

    /**
     * Post a comment on a pull request — inline when a path and line are given.
     *
     * Inline comments are anchored to a commit, so `$commitSha` is required
     * whenever a path or line is passed.
     *
     * @return int The provider's id for the created comment.
     */
    public function postComment(int $installationId, string $repositoryFullName, int $pullRequestNumber, string $body, ?string $path = null, ?int $line = null, ?string $commitSha = null): int;

    /**
     * Publish a neutral check run (never pass/fail) against a commit.
     *
     * @return int The provider's id for the created check run.
     */
    public function checkRun(int $installationId, string $repositoryFullName, string $headSha, string $name, string $summary): int;
}

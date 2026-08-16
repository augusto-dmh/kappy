<?php

namespace Modules\Review\Actions;

use Illuminate\Http\Client\RequestException;
use Modules\GitHubApp\Contracts\ScmDriver;
use Modules\Review\Enums\FindingStatus;
use Modules\Review\Models\Finding;
use Modules\Review\Models\Review;
use Modules\Review\Support\InlinePostingPolicy;

/**
 * Posts a generated review to the SCM host: one marked summary comment,
 * capped severity-gated inlines, and a neutral check run. GitHub ids are
 * stored as they land so a later retry skips work that already succeeded.
 */
class PostGeneratedReview
{
    public function __construct(private ScmDriver $scmDriver) {}

    public function execute(Review $review): void
    {
        $review->loadMissing(['findings', 'pullRequest.repository.installation']);

        $installationId = $review->pullRequest->repository->installation->github_installation_id;
        $repositoryFullName = $review->pullRequest->repository->full_name;
        $pullRequestNumber = $review->pullRequest->github_pr_number;

        $split = InlinePostingPolicy::split($review->findings);

        if ($review->summary_comment_id === null) {
            $review->update([
                'summary_comment_id' => $this->scmDriver->postComment(
                    $installationId,
                    $repositoryFullName,
                    $pullRequestNumber,
                    $this->marked($this->summaryBody($review, $split['folded'])),
                ),
            ]);
        }

        foreach ($split['inline'] as $finding) {
            if ($finding->github_comment_id !== null) {
                continue;
            }

            try {
                $commentId = $this->scmDriver->postComment(
                    $installationId,
                    $repositoryFullName,
                    $pullRequestNumber,
                    $this->marked($this->inlineBody($finding)),
                    $finding->path,
                    $finding->line,
                    $review->head_sha,
                );
            } catch (RequestException $e) {
                if ($e->response->status() === 422) {
                    continue;
                }

                throw $e;
            }

            $finding->update([
                'github_comment_id' => $commentId,
                'status' => FindingStatus::Posted,
            ]);
        }

        if ($review->github_check_run_id === null) {
            $review->update([
                'github_check_run_id' => $this->scmDriver->checkRun(
                    $installationId,
                    $repositoryFullName,
                    $review->head_sha,
                    'kappy-review',
                    (string) $review->summary_overview,
                ),
            ]);
        }
    }

    /**
     * @param  iterable<int, Finding>  $folded
     */
    private function summaryBody(Review $review, iterable $folded): string
    {
        $sections = [
            (string) $review->summary_overview,
            (string) $review->summary_walkthrough,
        ];

        $foldedLines = collect($folded)->map(function (Finding $finding): string {
            $anchor = $finding->path !== null && $finding->path !== ''
                ? " {$finding->path}".($finding->line !== null ? ":{$finding->line}" : '')
                : '';

            return "- [{$finding->severity->value}] {$finding->title}{$anchor}";
        });

        if ($foldedLines->isNotEmpty()) {
            $sections[] = "Folded findings:\n".$foldedLines->implode("\n");
        }

        return implode("\n\n", array_filter($sections));
    }

    private function inlineBody(Finding $finding): string
    {
        $parts = [$finding->title, $finding->message];

        if (filled($finding->suggestion)) {
            $parts[] = $finding->suggestion;
        }

        return implode("\n\n", $parts);
    }

    private function marked(string $body): string
    {
        return config('kappy.review.ai_marker')."\n".$body;
    }
}

<?php

namespace Modules\Review\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Models\Finding;
use Modules\Review\Models\Review;

class ReviewController
{
    public function index(Request $request): Response
    {
        $reviews = Review::query()
            ->whereHas(
                'pullRequest.repository.installation.account.memberships',
                fn ($query) => $query->where('user_id', $request->user()->id),
            )
            ->with(['pullRequest.repository'])
            ->withCount('findings')
            ->withCount([
                'findings as findings_critical_count' => fn ($query) => $query->where('severity', FindingSeverity::Critical),
                'findings as findings_high_count' => fn ($query) => $query->where('severity', FindingSeverity::High),
                'findings as findings_medium_count' => fn ($query) => $query->where('severity', FindingSeverity::Medium),
                'findings as findings_low_count' => fn ($query) => $query->where('severity', FindingSeverity::Low),
                'findings as findings_nit_count' => fn ($query) => $query->where('severity', FindingSeverity::Nit),
            ])
            ->latest()
            ->get()
            ->map(fn (Review $review): array => $this->listRow($review));

        return Inertia::render('review::index', [
            'reviews' => $reviews->values(),
        ]);
    }

    public function show(Review $review): Response
    {
        $review->load([
            'pullRequest.repository.installation.account',
            'findings',
        ]);

        Gate::authorize('view', $review->pullRequest->repository->installation->account);

        $review->loadCount([
            'findings',
            'findings as findings_critical_count' => fn ($query) => $query->where('severity', FindingSeverity::Critical),
            'findings as findings_high_count' => fn ($query) => $query->where('severity', FindingSeverity::High),
            'findings as findings_medium_count' => fn ($query) => $query->where('severity', FindingSeverity::Medium),
            'findings as findings_low_count' => fn ($query) => $query->where('severity', FindingSeverity::Low),
            'findings as findings_nit_count' => fn ($query) => $query->where('severity', FindingSeverity::Nit),
        ]);

        return Inertia::render('review::show', [
            'review' => array_merge($this->listRow($review), [
                'summary_overview' => $review->summary_overview,
                'summary_walkthrough' => $review->summary_walkthrough,
                'failure_reason' => $review->failure_reason,
                'failure_reason_label' => $this->humanizeFailureReason($review->failure_reason),
                'findings' => $review->findings
                    ->sort(function (Finding $left, Finding $right): int {
                        return $right->severity->rank() <=> $left->severity->rank()
                            ?: strcmp($left->path, $right->path)
                            ?: ($left->line ?? 0) <=> ($right->line ?? 0);
                    })
                    ->values()
                    ->map(fn (Finding $finding): array => [
                        'id' => $finding->id,
                        'category' => $finding->category->value,
                        'severity' => $finding->severity->value,
                        'path' => $finding->path,
                        'line' => $finding->line,
                        'title' => $finding->title,
                        'message' => $finding->message,
                        'suggestion' => $finding->suggestion,
                        'agent_prompt' => $finding->agent_prompt,
                    ]),
            ]),
        ]);
    }

    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     inbox_group: string,
     *     repository_full_name: string,
     *     pull_request_number: int,
     *     pull_request_title: string,
     *     summary_risk_level: string|null,
     *     findings_count: int,
     *     findings_severity: array{critical: int, high: int, medium: int, low: int, nit: int},
     *     timestamp: string|null
     * }
     */
    private function listRow(Review $review): array
    {
        $timestamp = $review->finished_at ?? $review->started_at ?? $review->created_at;

        return [
            'id' => $review->id,
            'status' => $review->status->value,
            'inbox_group' => $review->status->inboxGroup(),
            'repository_full_name' => $review->pullRequest->repository->full_name,
            'pull_request_number' => $review->pullRequest->github_pr_number,
            'pull_request_title' => $review->pullRequest->title,
            'summary_risk_level' => $review->summary_risk_level?->value,
            'findings_count' => (int) $review->findings_count,
            'findings_severity' => [
                'critical' => (int) $review->findings_critical_count,
                'high' => (int) $review->findings_high_count,
                'medium' => (int) $review->findings_medium_count,
                'low' => (int) $review->findings_low_count,
                'nit' => (int) $review->findings_nit_count,
            ],
            'timestamp' => $timestamp?->toIso8601String(),
        ];
    }

    private function humanizeFailureReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        return match ($reason) {
            'scm_unreachable' => 'GitHub could not be reached.',
            'provider_timeout' => 'The review model timed out.',
            'provider_error' => 'The review model returned an error.',
            'review_failed' => 'The review could not be completed.',
            'diff_exceeds_limit' => 'The pull request diff exceeded the size limit.',
            'empty_diff' => 'The pull request diff was empty.',
            default => 'This review could not be completed.',
        };
    }
}

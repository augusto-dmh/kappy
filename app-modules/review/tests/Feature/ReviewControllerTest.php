<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;
use Modules\Identity\Models\Membership;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\RiskLevel;
use Modules\Review\Models\Finding;
use Modules\Review\Models\Review;

/**
 * @return array{user: User, review: Review, repository: Repository, pullRequest: PullRequest}
 */
function memberReview(array $repository = [], ?callable $review = null): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create();
    Membership::factory()->for($user)->for($account)->owner()->create();
    $installation = Installation::factory()->for($account)->create();
    $repositoryModel = Repository::factory()->for($installation)->create(array_merge(
        ['full_name' => 'acme/inbox'],
        $repository,
    ));
    $pullRequest = PullRequest::factory()->for($repositoryModel)->create([
        'github_pr_number' => 12,
        'title' => 'Seed the review inbox',
    ]);

    $factory = Review::factory()->for($pullRequest);
    $reviewModel = $review instanceof Closure || is_callable($review)
        ? $review($factory)->create()
        : $factory->create();

    return [
        'user' => $user,
        'review' => $reviewModel,
        'repository' => $repositoryModel,
        'pullRequest' => $pullRequest,
    ];
}

test('guests are redirected to the login page on the reviews index', function () {
    $this->get(route('reviews.index'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to the login page on the review show page', function () {
    $fixtures = memberReview(review: fn ($factory) => $factory->completed());

    $this->get(route('reviews.show', $fixtures['review']))
        ->assertRedirect(route('login'));
});

test('authenticated users with no reviews see an empty inbox', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('review::index')
            ->has('reviews', 0)
        );
});

test('the reviews index lists only reviews belonging to the authenticated user', function () {
    $mine = memberReview(
        ['full_name' => 'mine/repo'],
        fn ($factory) => $factory->completed(),
    );

    $theirs = memberReview(
        ['full_name' => 'theirs/repo'],
        fn ($factory) => $factory->completed(),
    );

    $this->actingAs($mine['user'])
        ->get(route('reviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('review::index')
            ->has('reviews', 1)
            ->where('reviews.0.id', $mine['review']->id)
            ->where('reviews.0.repository_full_name', 'mine/repo')
            ->where('reviews.0.pull_request_number', 12)
            ->where('reviews.0.pull_request_title', 'Seed the review inbox')
            ->where('reviews.0.status', 'completed')
            ->where('reviews.0.inbox_group', 'completed')
            ->where('reviews.0.summary_risk_level', null)
            ->where('reviews.0.timestamp', $mine['review']->fresh()->finished_at->toIso8601String())
        );

    expect($theirs['review']->id)->not->toBe($mine['review']->id);
});

test('the reviews index includes findings_count and omits agent_prompt', function () {
    $fixtures = memberReview(review: fn ($factory) => $factory->completed());
    Finding::factory()->count(3)->for($fixtures['review'])->create();

    $this->actingAs($fixtures['user'])
        ->get(route('reviews.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviews.0.findings_count', 3)
            ->where('reviews.0.findings_severity.medium', 3)
            ->missing('reviews.0.agent_prompt')
            ->missing('reviews.0.findings')
        );
});

test('the reviews index lists failed skipped and in-progress reviews with ids', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    Membership::factory()->for($user)->for($account)->owner()->create();
    $installation = Installation::factory()->for($account)->create();
    $repository = Repository::factory()->for($installation)->create();
    $pullRequest = PullRequest::factory()->for($repository)->create();

    $failed = Review::factory()->for($pullRequest)->failed()->create();
    $skipped = Review::factory()->for($pullRequest)->skipped()->create(['head_sha' => fake()->unique()->sha1()]);
    $queued = Review::factory()->for($pullRequest)->create(['head_sha' => fake()->unique()->sha1()]);

    $this->actingAs($user)
        ->get(route('reviews.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviews', 3)
            ->where('reviews', function ($reviews) use ($failed, $skipped, $queued) {
                $rows = collect($reviews);

                return $rows->pluck('id')->sort()->values()->all() === collect([
                    $failed->id,
                    $skipped->id,
                    $queued->id,
                ])->sort()->values()->all()
                    && $rows->firstWhere('id', $failed->id)['inbox_group'] === 'failed'
                    && $rows->firstWhere('id', $skipped->id)['inbox_group'] === 'skipped'
                    && $rows->firstWhere('id', $queued->id)['inbox_group'] === 'in_progress'
                    && $rows->firstWhere('id', $queued->id)['summary_risk_level'] === null
                    && $rows->firstWhere('id', $queued->id)['findings_count'] === 0
                    && filled($rows->firstWhere('id', $failed->id)['timestamp'])
                    && $rows->every(fn ($row) => filled($row['id']));
            })
        );
});

test('a member can view their own review with summaries and an agent prompt', function () {
    $fixtures = memberReview(review: fn ($factory) => $factory->completed()->state([
        'summary_overview' => 'Overview of the change.',
        'summary_walkthrough' => 'Walk through the files.',
        'summary_risk_level' => RiskLevel::Medium,
    ]));

    $prompted = Finding::factory()->for($fixtures['review'])->withAgentPrompt()->create([
        'severity' => FindingSeverity::High,
        'path' => 'src/B.php',
        'line' => 20,
        'agent_prompt' => 'Fix the race in src/B.php:20.',
    ]);
    Finding::factory()->for($fixtures['review'])->nit()->create([
        'path' => 'src/A.php',
        'line' => 3,
    ]);
    Finding::factory()->for($fixtures['review'])->create([
        'severity' => FindingSeverity::Critical,
        'path' => 'src/C.php',
        'line' => 1,
        'agent_prompt' => 'Fix the critical bug.',
    ]);
    Finding::factory()->for($fixtures['review'])->create([
        'severity' => FindingSeverity::High,
        'path' => 'src/A.php',
        'line' => 10,
        'agent_prompt' => 'Fix A at line 10.',
    ]);
    Finding::factory()->for($fixtures['review'])->create([
        'severity' => FindingSeverity::High,
        'path' => 'src/A.php',
        'line' => 2,
        'agent_prompt' => 'Fix A at line 2.',
    ]);

    $this->actingAs($fixtures['user'])
        ->get(route('reviews.show', $fixtures['review']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('review::show')
            ->where('review.id', $fixtures['review']->id)
            ->where('review.status', 'completed')
            ->where('review.summary_overview', 'Overview of the change.')
            ->where('review.summary_walkthrough', 'Walk through the files.')
            ->where('review.summary_risk_level', 'medium')
            ->has('review.findings', 5)
            ->where('review.findings.0.severity', 'critical')
            ->where('review.findings.0.path', 'src/C.php')
            ->where('review.findings.1.severity', 'high')
            ->where('review.findings.1.path', 'src/A.php')
            ->where('review.findings.1.line', 2)
            ->where('review.findings.2.severity', 'high')
            ->where('review.findings.2.path', 'src/A.php')
            ->where('review.findings.2.line', 10)
            ->where('review.findings.3.severity', 'high')
            ->where('review.findings.3.path', 'src/B.php')
            ->where('review.findings.3.agent_prompt', 'Fix the race in src/B.php:20.')
            ->where('review.findings.4.severity', 'nit')
            ->where('review.findings.4.agent_prompt', null)
        );

    expect($prompted->agent_prompt)->toBe('Fix the race in src/B.php:20.');
});

test('a member is forbidden from viewing another account\'s review', function () {
    $stranger = User::factory()->create();
    $fixtures = memberReview(review: fn ($factory) => $factory->completed());

    $this->actingAs($stranger)
        ->get(route('reviews.show', $fixtures['review']))
        ->assertForbidden();
});

test('an unknown review ulid returns not found', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reviews.show', '01h00000000000000000000000'))
        ->assertNotFound();
});

test('a failed review show page includes a humanized failure reason', function () {
    $fixtures = memberReview(review: fn ($factory) => $factory->failed());

    $this->actingAs($fixtures['user'])
        ->get(route('reviews.show', $fixtures['review']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('review.failure_reason', 'provider_timeout')
            ->where('review.failure_reason_label', 'The review model timed out.')
            ->missing('review.exception')
            ->missing('review.diff')
        );
});

test('an in-progress review show page does not require summaries', function () {
    $fixtures = memberReview();

    $this->actingAs($fixtures['user'])
        ->get(route('reviews.show', $fixtures['review']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('review::show')
            ->where('review.status', 'queued')
            ->where('review.inbox_group', 'in_progress')
            ->where('review.summary_overview', null)
            ->where('review.summary_walkthrough', null)
            ->has('review.findings', 0)
        );
});

test('an unknown failure reason is humanized with a generic fallback', function () {
    $fixtures = memberReview(review: fn ($factory) => $factory->failed()->state([
        'failure_reason' => 'not_a_real_reason',
    ]));

    $this->actingAs($fixtures['user'])
        ->get(route('reviews.show', $fixtures['review']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('review.failure_reason', 'not_a_real_reason')
            ->where('review.failure_reason_label', 'This review could not be completed.')
        );
});

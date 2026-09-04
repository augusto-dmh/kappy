<?php

namespace Modules\Review\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;
use Modules\Identity\Models\Membership;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\RiskLevel;
use Modules\Review\Models\Finding;
use Modules\Review\Models\Review;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::query()->where('email', 'test@example.com')->first()
            ?? User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $account = Account::factory()->create([
            'name' => 'Acme',
            'github_login' => 'acme',
        ]);

        Membership::factory()->for($user)->for($account)->owner()->create();

        $installation = Installation::factory()->for($account)->create();
        $api = Repository::factory()->for($installation)->create(['full_name' => 'acme/api']);
        $web = Repository::factory()->for($installation)->create(['full_name' => 'acme/web']);

        $heroPullRequest = PullRequest::factory()->for($api)->create([
            'github_pr_number' => 42,
            'title' => 'Harden the webhook receiver',
        ]);

        $hero = Review::factory()->for($heroPullRequest)->completed()->create([
            'summary_overview' => 'The webhook path authenticates deliveries and records them, but the retry window can drop a burst.',
            'summary_walkthrough' => 'Start at the signature check, then the idempotency key, then the queue hop onto reviews.',
            'summary_risk_level' => RiskLevel::High,
        ]);

        Finding::factory()->for($hero)->create([
            'severity' => FindingSeverity::Critical,
            'path' => 'app-modules/github-app/src/Http/Controllers/GithubWebhookController.php',
            'line' => 18,
            'title' => 'Signature comparison is not constant-time',
            'message' => 'A naive string compare leaks timing on the GitHub signature.',
            'suggestion' => 'Compare hashes with hash_equals.',
            'agent_prompt' => 'In GithubWebhookController around line 18, replace the signature equality check with hash_equals so the comparison is constant-time.',
        ]);
        Finding::factory()->for($hero)->create([
            'severity' => FindingSeverity::High,
            'path' => 'app-modules/review/src/Jobs/ProcessReview.php',
            'line' => 40,
            'title' => 'Claim can race two workers',
            'message' => 'Two workers can claim the same queued review without a lock.',
            'suggestion' => 'Claim with an atomic status update.',
            'agent_prompt' => 'In ProcessReview, claim the queued review with a single atomic update so only one worker proceeds.',
        ]);
        Finding::factory()->for($hero)->create([
            'severity' => FindingSeverity::Medium,
            'path' => 'app-modules/review/src/Actions/PersistGeneratedReview.php',
            'line' => 22,
            'title' => 'Summary fields can be left empty',
            'message' => 'A completed review may have null overview and walkthrough.',
            'suggestion' => 'Require summaries before marking completed.',
            'agent_prompt' => 'When persisting a generated review, require summary_overview and summary_walkthrough before setting status to completed.',
        ]);
        Finding::factory()->for($hero)->create([
            'severity' => FindingSeverity::Low,
            'path' => 'app-modules/review/src/Models/Review.php',
            'line' => 72,
            'title' => 'Missing index hint in relation docblock',
            'message' => 'The pullRequest relation is fine; the docblock could name the foreign key.',
            'suggestion' => 'Document pull_request_id on the relation.',
            'agent_prompt' => 'On Review::pullRequest, mention pull_request_id in the relation docblock so callers know the foreign key.',
        ]);
        Finding::factory()->for($hero)->nit()->create([
            'path' => 'app-modules/review/src/Enums/ReviewStatus.php',
            'line' => 7,
            'title' => 'Queued is the default; say so in a comment',
            'message' => 'Readers have to check the migration to learn the default status.',
            'suggestion' => 'Note the default in the enum docblock.',
        ]);

        Review::factory()->for(PullRequest::factory()->for($web)->create([
            'github_pr_number' => 7,
            'title' => 'Add dark mode toggle',
        ]))->failed()->create();

        Review::factory()->for(PullRequest::factory()->for($web)->create([
            'github_pr_number' => 8,
            'title' => 'Empty docs-only change',
        ]))->skipped()->create();

        Review::factory()->for(PullRequest::factory()->for($api)->create([
            'github_pr_number' => 43,
            'title' => 'Retry webhook deliveries',
        ]))->create();
    }
}

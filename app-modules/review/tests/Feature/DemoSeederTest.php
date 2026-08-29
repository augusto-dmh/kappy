<?php

use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Modules\Review\Database\Seeders\DemoSeeder;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Models\Review;

test('the demo seeder creates a walkable inbox without dispatching review jobs', function () {
    Bus::fake();

    User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $this->seed(DemoSeeder::class);

    Bus::assertNothingDispatched();

    $user = User::query()->where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->accounts()->count())->toBe(1);

    $reviews = Review::query()->with(['pullRequest.repository', 'findings'])->get();

    expect($reviews)->toHaveCount(4)
        ->and($reviews->pluck('pullRequest.repository.full_name')->unique()->sort()->values()->all())
        ->toBe(['acme/api', 'acme/web'])
        ->and($reviews->pluck('status')->map->value->all())
        ->toContain(ReviewStatus::Completed->value)
        ->toContain(ReviewStatus::Failed->value)
        ->toContain(ReviewStatus::Skipped->value)
        ->toContain(ReviewStatus::Queued->value);

    $hero = $reviews->firstWhere('status', ReviewStatus::Completed);

    expect($hero)->not->toBeNull()
        ->and($hero->summary_overview)->not->toBeNull()
        ->and($hero->summary_walkthrough)->not->toBeNull()
        ->and($hero->summary_risk_level)->not->toBeNull()
        ->and($hero->findings)->toHaveCount(5)
        ->and($hero->findings->contains(fn ($finding) => $finding->severity === FindingSeverity::Nit && $finding->agent_prompt === null))->toBeTrue()
        ->and($hero->findings->contains(fn ($finding) => $finding->agent_prompt !== null))->toBeTrue();
});

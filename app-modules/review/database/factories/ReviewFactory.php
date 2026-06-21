<?php

namespace Modules\Review\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\GitHubApp\Models\PullRequest;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Enums\ReviewTrigger;
use Modules\Review\Models\Review;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Review>
     */
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pull_request_id' => PullRequest::factory(),
            'head_sha' => fake()->sha1(),
            'trigger' => ReviewTrigger::PrOpened,
            'status' => ReviewStatus::Queued,
            'is_incremental' => false,
        ];
    }

    /**
     * Indicate that the review completed successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Completed,
            'generator_model' => 'claude-opus-4-8',
            'input_tokens' => fake()->numberBetween(1_000, 50_000),
            'output_tokens' => fake()->numberBetween(500, 10_000),
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    /**
     * Indicate that the review failed with a redacted reason.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Failed,
            'failure_reason' => 'provider_timeout',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    /**
     * Indicate that the review was skipped.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Skipped,
            'failure_reason' => 'empty_diff',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}

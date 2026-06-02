<?php

namespace Modules\GitHubApp\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\GitHubApp\Enums\PullRequestState;
use Modules\GitHubApp\Models\PullRequest;
use Modules\GitHubApp\Models\Repository;

/**
 * @extends Factory<PullRequest>
 */
class PullRequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PullRequest>
     */
    protected $model = PullRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'github_pr_number' => fake()->unique()->numberBetween(1, 100_000),
            'title' => fake()->sentence(),
            'author_login' => fake()->userName(),
            'base_sha' => fake()->sha1(),
            'head_sha' => fake()->sha1(),
            'state' => PullRequestState::Open,
            'linked_issue_ref' => null,
        ];
    }

    /**
     * Indicate that the pull request is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => PullRequestState::Closed,
        ]);
    }

    /**
     * Indicate that the pull request was merged.
     */
    public function merged(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => PullRequestState::Merged,
        ]);
    }
}

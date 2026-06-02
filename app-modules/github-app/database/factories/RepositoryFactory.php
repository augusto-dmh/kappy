<?php

namespace Modules\GitHubApp\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\Repository;

/**
 * @extends Factory<Repository>
 */
class RepositoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Repository>
     */
    protected $model = Repository::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'installation_id' => Installation::factory(),
            'github_repo_id' => fake()->unique()->numberBetween(1, 1_000_000_000),
            'full_name' => fake()->userName().'/'.fake()->slug(2),
            'private' => fake()->boolean(),
            'default_branch' => 'main',
            'review_enabled' => true,
        ];
    }

    /**
     * Indicate that the repository is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'private' => true,
        ]);
    }

    /**
     * Indicate that review is disabled for the repository.
     */
    public function reviewDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'review_enabled' => false,
        ]);
    }
}

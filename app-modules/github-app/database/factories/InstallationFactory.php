<?php

namespace Modules\GitHubApp\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\GitHubApp\Enums\InstallationTarget;
use Modules\GitHubApp\Enums\RepositorySelection;
use Modules\GitHubApp\Models\Installation;
use Modules\Identity\Models\Account;

/**
 * @extends Factory<Installation>
 */
class InstallationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Installation>
     */
    protected $model = Installation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'github_installation_id' => fake()->unique()->numberBetween(1, 1_000_000_000),
            'target_type' => InstallationTarget::User,
            'suspended_at' => null,
            'repositories_selection' => RepositorySelection::All,
        ];
    }

    /**
     * Indicate that the installation targets a GitHub organization.
     */
    public function organization(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => InstallationTarget::Organization,
        ]);
    }

    /**
     * Indicate that the installation is currently suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended_at' => now(),
        ]);
    }
}

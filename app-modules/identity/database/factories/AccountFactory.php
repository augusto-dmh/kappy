<?php

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Enums\AccountType;
use Modules\Identity\Models\Account;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Account>
     */
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => AccountType::Personal,
            'github_account_id' => fake()->unique()->numberBetween(1, 1_000_000_000),
            'github_login' => fake()->unique()->userName(),
            'name' => fake()->name(),
        ];
    }

    /**
     * Indicate that the account represents a GitHub organization.
     */
    public function organization(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountType::Organization,
        ]);
    }
}

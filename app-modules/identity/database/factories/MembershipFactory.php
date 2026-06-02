<?php

namespace Modules\Identity\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Enums\MembershipRole;
use Modules\Identity\Models\Account;
use Modules\Identity\Models\Membership;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Membership>
     */
    protected $model = Membership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'role' => MembershipRole::Member,
        ];
    }

    /**
     * Indicate that the membership grants the owner role.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => MembershipRole::Owner,
        ]);
    }
}

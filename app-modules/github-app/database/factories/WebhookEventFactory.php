<?php

namespace Modules\GitHubApp\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\WebhookEvent;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WebhookEvent>
     */
    protected $model = WebhookEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'installation_id' => Installation::factory(),
            'github_delivery_id' => fake()->unique()->uuid(),
            'event' => fake()->randomElement(['installation', 'installation_repositories', 'pull_request']),
            'action' => fake()->randomElement(['created', 'deleted', 'opened', 'synchronize']),
            'processed_at' => null,
        ];
    }

    /**
     * Indicate that the delivery has been processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => now(),
        ]);
    }

    /**
     * Indicate that the delivery could not be resolved to an installation.
     */
    public function unattached(): static
    {
        return $this->state(fn (array $attributes) => [
            'installation_id' => null,
        ]);
    }
}

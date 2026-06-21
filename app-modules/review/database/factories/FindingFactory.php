<?php

namespace Modules\Review\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Review\Enums\CriticVerdict;
use Modules\Review\Enums\FindingCategory;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\FindingStatus;
use Modules\Review\Models\Finding;
use Modules\Review\Models\Review;

/**
 * @extends Factory<Finding>
 */
class FindingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Finding>
     */
    protected $model = Finding::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $path = fake()->filePath();
        $message = fake()->sentence();

        return [
            'review_id' => Review::factory(),
            'category' => FindingCategory::Correctness,
            'severity' => FindingSeverity::Medium,
            'path' => $path,
            'line' => fake()->numberBetween(1, 500),
            'title' => fake()->sentence(4),
            'message' => $message,
            'suggestion' => fake()->optional()->sentence(),
            'agent_prompt' => fake()->sentence(),
            'confidence' => fake()->numberBetween(0, 100),
            'critic_verdict' => CriticVerdict::Pending,
            'critic_reason' => null,
            'status' => FindingStatus::Draft,
            'github_comment_id' => null,
            'fingerprint' => hash('sha256', $path."\n".$message),
        ];
    }

    /**
     * Indicate that the finding is a nitpick (no agent prompt).
     */
    public function nit(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::Nit,
            'agent_prompt' => null,
        ]);
    }

    /**
     * Indicate that the finding was posted to GitHub.
     */
    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::Posted,
            'github_comment_id' => fake()->unique()->numberBetween(1, 1_000_000_000),
        ]);
    }

    /**
     * Indicate that the finding carries an agent prompt.
     */
    public function withAgentPrompt(): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_prompt' => fake()->paragraph(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Environment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Environment>
 */
final class EnvironmentFactory extends Factory
{
    #[Override]
    protected $model = Environment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'key' => fake()->unique()->slug(1),
            'name' => fake()->word(),
        ];
    }
}

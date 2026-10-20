<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Flags\FlagType;
use App\Models\Flag;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * Makes the flag row alone. Settings are made by FlagWriter, which tests use to create flags the
 * way the API does.
 *
 * @extends Factory<Flag>
 */
final class FlagFactory extends Factory
{
    #[Override]
    protected $model = Flag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'description' => null,
            'type' => FlagType::Boolean,
            'variants' => Flag::BOOLEAN_VARIANTS,
        ];
    }
}

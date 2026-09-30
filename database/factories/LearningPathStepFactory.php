<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningPathStep>
 */
final class LearningPathStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_path_id' => LearningPath::factory(),
            'position'         => 1,
            'productable_type' => 'course',
            'productable_id'   => Course::factory(),
            'title'            => $this->faker->sentence(3),
            'description'      => $this->faker->paragraph(),
        ];
    }
}

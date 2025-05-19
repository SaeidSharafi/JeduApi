<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\CourseStatusEnum;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
final class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(),
            'full_name' => $this->faker->sentence(3),
            'short_name' => $this->faker->word(),
            'description' => $this->faker->paragraph(),
            'duration' => random_int(1, 100),
            'difficulty_level' => $this->faker->randomElement(CourseDifficultyLevelEnum::getAllValues()),
            'career_prospects_text' => $this->faker->sentence(10),
            'curriculum_summary_text' => $this->faker->words(5, true),
            'outcomes_json' => [
                'outcome1' => $this->faker->sentence(5),
                'outcome2' => $this->faker->sentence(5),
            ],
            'default_teacher_info' => $this->faker->words(5, true),
            'additional_info' => [],
            'meta_title' => $this->faker->words(5, true),
            'meta_description' =>$this->faker->paragraph(),
            'meta_keywords' => $this->faker->words(8, true),
            'properties' => [],
            'status' => $this->faker->randomElement(CourseStatusEnum::getAllValues()),
            'created_by' => Admin::factory(),
        ];
    }
}

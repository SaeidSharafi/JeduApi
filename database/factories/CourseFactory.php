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
            'full_name' => $this->faker->persianWords(3, true),
            'short_name' => $this->faker->persianWord(),
            'description' => $this->faker->persianParagraph(),
            'duration' => random_int(1, 100),
            'difficulty_level' => $this->faker->randomElement(CourseDifficultyLevelEnum::getAllValues()),
            'career_prospects_text' => $this->faker->persianParagraph(),
            'curriculum_summary_text' => $this->faker->persianWords(5, true),
            'outcomes_json' => [
                'outcome1' => $this->faker->persianSentences(5),
                'outcome2' => $this->faker->persianSentences(5),
            ],
            'default_teacher_info' => $this->faker->persianWords(5, true),
            'additional_info' => [],
            'meta_title' => $this->faker->persianWords(5, true),
            'meta_description' =>$this->faker->persianParagraph(),
            'meta_keywords' => $this->faker->persianWords(8, true),
            'properties' => [],
            'status' => $this->faker->randomElement(CourseStatusEnum::getAllValues()),
            'created_by' => Admin::factory(),
        ];
    }
}

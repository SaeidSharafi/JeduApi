<?php

namespace Database\Factories;

use App\Enums\CourseStatusEnum;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
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
            'name' => $this->faker->sentence(3),
            'short_name' => $this->faker->word(),
            'description' => $this->faker->paragraph(),
            'default_teacher_info' => $this->faker->paragraph(),
            'meta_title' => $this->faker->sentence(3),
            'meta_description' => $this->faker->sentence(10),
            'meta_keywords' => $this->faker->words(5, true),
            'status' => $this->faker->randomElement(CourseStatusEnum::getAllValues()),
            'created_by' => Admin::factory(),
        ];
    }
}

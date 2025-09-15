<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StudentStory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @mixin Factory<StudentStory> */
final class StudentStoryFactory extends Factory
{
    protected $model = StudentStory::class;

    public function definition(): array
    {
        return [
            'student_name'  => $this->faker->name(),
            'course_name'   => $this->faker->persianSentence(3),
            'course_url'    => $this->faker->url(),
            'story_text'    => $this->faker->persianParagraph(),
            'is_visible'    => $this->faker->boolean(80),
            'display_order' => $this->faker->numberBetween(1, 100),
        ];
    }
}

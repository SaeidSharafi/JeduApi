<?php

namespace Database\Factories;

use App\Enums\GenderEnum;
use App\Models\Course;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Plank\Mediable\Media;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'bio' => $this->faker->persianParagraph(),
            'rate' => $this->faker->randomFloat(2, 0, 5),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->mobile,
            'gender' => $this->faker->randomElement(GenderEnum::getAllValues()),
            'birth_date' => $this->faker->date(),
            'social_links' => [
                'facebook' => $this->faker->url(),
                'twitter' => $this->faker->url(),
                'linkedin' => $this->faker->url(),
            ],
            'user_id' => \App\Models\User::factory(),
        ];
    }

    /**
     * Attach fake SVG media with tag text to the course (state method)
     */
    public function withMedia(array $tags = ['profile']): self
    {
        return $this->afterCreating(function (Teacher $teacher) use ($tags) {
            foreach ($tags as $tag) {
                $media = Media::query()
                    ->where('directory', 'fake-media')
                    ->whereLike('filename', "%placeholder%")
                    ->where('extension', 'svg')
                    ->inRandomOrder()
                    ->first();
                $teacher->attachMedia($media, $tag);
            }
        });
    }
}

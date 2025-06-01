<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Staff;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Plank\Mediable\Media;

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
            'slug'                    => $this->faker->unique()->slug(),
            'full_name'               => $this->faker->persianWords(3, true),
            'short_name'              => $this->faker->persianWord(),
            'description'             => $this->faker->persianParagraph(),
            'duration'                => random_int(1, 100),
            'difficulty_level'        => $this->faker->randomElement(CourseDifficultyLevelEnum::getAllValues()),
            'career_prospects_text'   => $this->faker->persianParagraph(),
            'curriculum_summary_text' => $this->faker->persianWords(5, true),
            'outcomes_json'           => [
                'outcome1' => $this->faker->persianSentences(5),
                'outcome2' => $this->faker->persianSentences(5),
            ],
            'default_teacher_info' => $this->faker->persianWords(5, true),
            'additional_info'      => [],
            'meta_title'           => mb_trim(Str::take($this->faker->persianWords(4, true), 70)),
            'meta_description'     => mb_trim(Str::take($this->faker->persianParagraph(20, false), 100)),
            'meta_keywords'        => mb_trim(Str::take(implode(',', $this->faker->persianWords(3)), 255)),
            'properties'           => [],
            'status'               => $this->faker->randomElement(PublicationStatusEnum::getAllValues()),
            'created_by'           => Staff::factory(),
        ];
    }

    public function withCategory(int $categoryCount = 1): self
    {
        return $this->afterCreating(function (Course $course) use ($categoryCount) {
            if (Category::query()->count() < 10) {
                $course->categories()->attach(
                    Category::factory()->count($categoryCount)->create()
                );

                return;
            }

            $course->categories()->attach(
                Category::query()
                    ->inRandomOrder()
                    ->take($categoryCount)
                    ->get()
            );

        });
    }

    /**
     * Attach fake SVG media with tag text to the course (state method)
     */
    public function withMedia(array $tags = ['gallery']): self
    {
        return $this->afterCreating(function (Course $course) use ($tags) {
            foreach ($tags as $tag) {
                if ($tag === 'video') {
                    $media = Media::query()
                        ->where('directory', 'fake-media')
                        ->where('extension', 'mp4')
                        ->inRandomOrder()
                        ->first();
                    $course->attachMedia($media, $tag);

                    continue;
                }
                $media = Media::query()
                    ->where('directory', 'fake-media')
                    ->whereLike('filename', "%$tag%")
                    ->where('extension', 'svg')
                    ->inRandomOrder()
                    ->first();
                $course->attachMedia($media, $tag);
            }
        });
    }

    public function withDigitalAssets(int $count = 1, bool $optional = false): self
    {
        if ($optional) {
            if (random_int(0, 100) < 35) {
                return $this;
            }
        }

        return $this->afterCreating(function (Course $course) use ($count) {
            if (DigitalAsset::query()->count() < 20) {
                $course->digitalAssets()->attach(
                    DigitalAsset::factory()->count($count)->create()
                );

                return;
            }
            $course->digitalAssets()->attach(
                DigitalAsset::query()
                    ->inRandomOrder()
                    ->take($count)
                    ->get()
            );
        });
    }
}

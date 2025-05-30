<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Seminar;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Plank\Mediable\Media;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Seminar>
 */
final class SeminarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name'                => $this->faker->sentence(3),
            'short_name'               => $this->faker->sentence(2),
            'subtitle'                 => $this->faker->sentence(6),
            'slug'                     => $this->faker->slug(),
            'description'              => $this->faker->paragraph(),
            'learning_objectives'      => $this->faker->paragraph(),
            'target_audience'          => $this->faker->paragraph(),
            'prerequisites'            => $this->faker->paragraph(),
            'promo_video_external_url' => $this->faker->url(),
            'estimated_duration_desc'  => $this->faker->sentence(4),
            'level'                    => $this->faker->randomElement(CourseDifficultyLevelEnum::getAllValues()),
            'provides_certificate'     => $this->faker->boolean(),
            'faq'                      => [
                ['question' => $this->faker->sentence(), 'answer' => $this->faker->paragraph()],
                ['question' => $this->faker->sentence(), 'answer' => $this->faker->paragraph()],
            ],
            'keywords'         => implode(',', $this->faker->words(5)),
            'status'           => $this->faker->randomElement(PublicationStatusEnum::getAllValues()),
            'created_by'       => Admin::factory(),
            'meta_title'       => mb_trim(Str::take($this->faker->persianWords(4, true), 70)),
            'meta_description' => mb_trim(Str::take($this->faker->persianParagraph(20, false), 100)),
            'meta_keywords'    => mb_trim(Str::take(implode(',', $this->faker->persianWords(3)), 255)),
        ];
    }

    public function withCategory(int $categoryCount = 1): self
    {
        return $this->afterCreating(function (Seminar $seminar) use ($categoryCount) {
            if (Category::query()->count() < 10) {
                $seminar->categories()->attach(
                    Category::factory()->count($categoryCount)->create()
                );

                return;
            }

            $seminar->categories()->attach(
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
        return $this->afterCreating(function (Seminar $seminar) use ($tags) {
            foreach ($tags as $tag) {
                if ($tag === 'video') {
                    $media = Media::query()
                        ->where('directory', 'fake-media')
                        ->where('extension', 'mp4')
                        ->inRandomOrder()
                        ->first();
                    $seminar->attachMedia($media, $tag);

                    continue;
                }
                $media = Media::query()
                    ->where('directory', 'fake-media')
                    ->whereLike('filename', "%$tag%")
                    ->where('extension', 'svg')
                    ->inRandomOrder()
                    ->first();
                $seminar->attachMedia($media, $tag);
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

        return $this->afterCreating(function (Seminar $seminar) use ($count) {
            if (DigitalAsset::query()->count() < 20) {
                $seminar->digitalAssets()->attach(
                    DigitalAsset::factory()->count($count)->create()
                );

                return;
            }
            $seminar->digitalAssets()->attach(
                DigitalAsset::query()
                    ->inRandomOrder()
                    ->take($count)
                    ->get()
            );
        });
    }
}

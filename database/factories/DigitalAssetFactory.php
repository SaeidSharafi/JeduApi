<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Category;
use App\Models\DigitalAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Plank\Mediable\Media;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DigitalAsset>
 */
final class DigitalAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                    => $this->faker->persianWord(),
            'slug'                    => $this->faker->unique()->slug(),
            'description'             => $this->faker->persianSentence(),
            'version'                 => $this->faker->word(),
            'page_count'              => $this->faker->numberBetween(1, 100),
            'duration_seconds'        => $this->faker->numberBetween(60, 3600),
            'is_attachable_to_course' => true,
            'status'                  => \App\Enums\PublicationStatusEnum::DRAFT->value,
            'keywords'                => implode(',', $this->faker->persianWords(3)),
            'meta_title'              => mb_trim(Str::take($this->faker->persianWords(4, true), 70)),
            'meta_description'        => mb_trim(Str::take($this->faker->persianParagraph(20, false), 100)),
            'meta_keywords'           => mb_trim(Str::take(implode(',', $this->faker->persianWords(3)), 255)),
            'published_at'            => $this->faker->dateTime()?->format('Y-m-d H:i:s'),
            'created_by'              => Admin::factory(),
        ];
    }

    public function nonAttachable(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'is_attachable_to_course' => false,
            ];
        });
    }

    public function withFile(): self
    {
        return $this->afterCreating(function (DigitalAsset $digitalAsset) {
            $media = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%main%')
                ->where('extension', 'svg')
                ->inRandomOrder()
                ->first();
            $digitalAsset->attachMedia($media, 'main');

            $media = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%preview%')
                ->where('extension', 'svg')
                ->inRandomOrder()
                ->first();
            $digitalAsset->attachMedia($media, 'preview');
        });
    }

    public function withCategory(int $categoryCount = 1): self
    {
        return $this->afterCreating(function (DigitalAsset $digitalAsset) use ($categoryCount) {
            if (Category::query()->count() < 10) {
                $digitalAsset->categories()->attach(
                    Category::factory()->count($categoryCount)->create()
                );

                return;
            }

            $digitalAsset->categories()->attach(
                Category::query()
                    ->inRandomOrder()
                    ->take($categoryCount)
                    ->get()
            );

        });
    }
}

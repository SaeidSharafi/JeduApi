<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Admin;
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
            'name' => $this->faker->persianWord(),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->persianSentence(),
            'version' => $this->faker->word(),
            'page_count' => $this->faker->numberBetween(1, 100),
            'duration_seconds' => $this->faker->numberBetween(60, 3600),
            'is_attachable_to_course' => $this->faker->boolean(),
            'status' => \App\Enums\PublicationStatusEnum::DRAFT->value,
            'keywords' => implode(',', $this->faker->words(3)),
            'meta_title' => mb_trim(Str::take($this->faker->words(4, true), 70)),
            'meta_description' => mb_trim(Str::take($this->faker->paragraph(), 160)),
            'meta_keywords' => mb_trim(Str::take(implode(',', $this->faker->words(3)), 255)),
            'published_at' => $this->faker->optional()->dateTime()?->format('Y-m-d H:i:s'),
            'created_by' => Admin::factory(),
        ];
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
}

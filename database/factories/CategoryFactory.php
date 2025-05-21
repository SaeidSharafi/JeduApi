<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Plank\Mediable\Media;

/** @mixin Factory<Category> */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->persianWord(),
            'slug' => $this->faker->unique()->slug,
            'status' => \App\Enums\PublicationStatusEnum::PUBLISHED,
            'description' => $this->faker->text,
            'image_url' => $this->faker->imageUrl(),
            'icon_url' => $this->faker->imageUrl(),
            'color_scheme' => $this->faker->hexColor,
            'meta_title' => $this->faker->persianSentence(),
            'meta_description' => $this->faker->persianText(),
            'meta_keywords' => $this->faker->persianWords(3, true),
            'properties' => [],
            'additional_info' => [],
            'created_by' => \App\Models\Admin::factory(),
        ];
    }

    public function withImage(): static
    {
        return $this->afterCreating(function (Category $category) {
            $media = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%placeholder%')
                ->where('extension', 'svg')
                ->inRandomOrder()
                ->first();
            $category->attachMedia($media, 'image');
            $category->image_url = $media->getUrl();
            $category->save();
        });
    }

    public function withIcon(): static
    {
        return $this->afterCreating(function (Category $category) {
            $media = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%icon%')
                ->where('extension', 'svg')
                ->inRandomOrder()
                ->first();
            $category->attachMedia($media, 'icon');
            $category->icon_url = $media->getUrl();
            $category->save();

        });
    }

    /**
     * Attach fake SVG media with tag text to the course (state method)
     */
    public function withMedia(array $tags = ['gallery']): static
    {
        return $this->afterCreating(function (Category $category) use ($tags) {
            foreach ($tags as $tag) {
                $media = Media::query()
                    ->where('directory', 'fake-media')
                    ->whereLike('filename', "%$tag%")
                    ->where('extension', 'svg')
                    ->inRandomOrder()
                    ->first();
                $category->attachMedia($media, $tag);
            }
        });
    }
}

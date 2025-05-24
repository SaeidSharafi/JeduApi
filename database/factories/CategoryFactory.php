<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Plank\Mediable\Media;

/** @mixin Factory<Category> */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {

        return [
            'name'             => $this->faker->unique()->persianWord(),
            'slug'             => $this->faker->unique()->slug,
            'status'           => \App\Enums\PublicationStatusEnum::PUBLISHED,
            'description'      => $this->faker->text,
            'image_url'        => $this->faker->imageUrl(),
            'icon_url'         => $this->faker->imageUrl(),
            'color_scheme'     => $this->faker->hexColor,
            'meta_title'       => mb_trim(Str::take($this->faker->persianWords(4, true), 70)),
            'meta_description' => mb_trim(Str::take($this->faker->persianParagraph(), 160)),
            'meta_keywords'    => mb_trim(Str::take(implode(',', $this->faker->persianWords(3)), 255)),
            'properties'       => [],
            'additional_info'  => [],
            'created_by'       => \App\Models\Admin::factory(),
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

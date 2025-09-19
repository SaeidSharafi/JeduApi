<?php

namespace Database\Factories\Blog;

use App\Enums\MediaTagEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Blog\BlogPost;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Plank\Mediable\Media;

/** @mixin Factory<BlogPost> */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        return [
            'title'                 => $this->faker->word(),
            'slug'                  => $this->faker->slug(),
            'body'                  => $this->faker->word(),
            'excerpt'               => $this->faker->word(),
            'status'                => $this->faker->randomElement(PublicationStatusEnum::cases()),
            'published_at'          => Carbon::now(),
            'read_time_minutes'     => $this->faker->randomNumber(),
            'is_featured'           => $this->faker->boolean(),
            'cover_image_url'       => $this->faker->imageUrl(),
            'main_productable_id'   => null,
            'main_productable_type' => null,
            'created_at'            => Carbon::now(),
            'updated_at'            => Carbon::now(),

            'author_id' => Staff::factory(),
        ];
    }

    /**
     * Attach fake SVG media with tag text to the course (state method)
     */
    public function withMedia(): self
    {
        return $this->afterCreating(function (BlogPost $blogPost) {
            $media = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%placeholder%')
                ->where('extension', 'svg')
                ->inRandomOrder()
                ->first();
            $blogPost->attachMedia($media, MediaTagEnum::GALLERY->value);

            $video = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%placeholder%')
                ->where('extension', 'mp4')
                ->inRandomOrder()
                ->first();
            $blogPost->attachMedia($video, MediaTagEnum::VIDEO->value);

            $cover = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%placeholder%')
                ->where('extension', 'svg')
                ->inRandomOrder()
                ->first();
            $blogPost->attachMedia($cover, MediaTagEnum::COVER->value);
            $blogPost->cover_image_url = $cover->getUrl();
            $blogPost->save();
        });
    }
}

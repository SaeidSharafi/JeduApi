<?php

namespace Database\Factories\Blog;

use App\Enums\PublicationStatusEnum;
use App\Models\Blog\BlogPost;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
            'main_productable_id'   => null,
            'main_productable_type' => null,
            'created_at'            => Carbon::now(),
            'updated_at'            => Carbon::now(),

            'author_id' => Staff::factory(),
        ];
    }
}

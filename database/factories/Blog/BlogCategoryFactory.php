<?php

namespace Database\Factories\Blog;

use App\Models\Blog\BlogCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** @mixin Factory<BlogCategory> */
class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategory::class;

    public function definition(): array
    {
        return [
            'name'             => $this->faker->name(),
            'slug'             => $this->faker->slug(),
            'description'      => $this->faker->text(),
            'meta_title'       => mb_trim(Str::take($this->faker->persianWords(4, true), 70)),
            'meta_description' => mb_trim(Str::take($this->faker->persianParagraph(20, false), 100)),
            'meta_keywords'    => mb_trim(Str::take(implode(',', $this->faker->persianWords(3)), 255)),
            'icon'             => $this->faker->imageUrl(),
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),

            'parent_id' => null,
        ];
    }
}

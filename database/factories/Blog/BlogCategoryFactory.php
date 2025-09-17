<?php

namespace Database\Factories\Blog;

use App\Models\Blog\BlogCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @mixin Factory<BlogCategory> */
class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategory::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->name(),
            'slug'        => $this->faker->slug(),
            'description' => $this->faker->text(),
            'icon'        => $this->faker->imageUrl(),
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),

            'parent_id' => null,
        ];
    }
}

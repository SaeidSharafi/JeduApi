<?php

namespace Database\Factories;

use App\Models\Slider;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @mixin Factory<Slider> */
class SliderFactory extends Factory
{
    protected $model = Slider::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'caption' => $this->faker->optional()->sentence,
            'image_url' => $this->faker->imageUrl(800, 600, 'nature', true),
            'image_alt' => $this->faker->optional()->sentence,
            'link' => $this->faker->optional()->url,
            'order' => $this->faker->numberBetween(0, 100),
        ];
    }
}

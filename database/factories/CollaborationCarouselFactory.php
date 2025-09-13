<?php

namespace Database\Factories;

use App\Enums\CollaborationCarouselShowInEnum;
use App\Models\CollaborationCarousel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @mixin Factory<CollaborationCarousel> */
class CollaborationCarouselFactory extends Factory
{
    protected $model = CollaborationCarousel::class;

    public function definition(): array
    {
        return [
            'title'     => $this->faker->sentence,
            'caption'   => $this->faker->optional()->sentence,
            'image_url' => $this->faker->imageUrl(800, 600, 'business', true),
            'image_alt' => $this->faker->optional()->sentence,
            'url'       => $this->faker->optional()->url,
            'show_in'   => $this->faker->randomElement(CollaborationCarouselShowInEnum::cases()),
            'order'     => $this->faker->numberBetween(0, 100),
            'is_active'    => $this->faker->boolean(80), // 80% chance of being true
        ];
    }
}

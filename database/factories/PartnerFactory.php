<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PartnerShowInEnum;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @mixin Factory<Partner> */
final class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'title'     => $this->faker->sentence,
            'caption'   => $this->faker->optional()->sentence,
            'image_url' => $this->faker->imageUrl(800, 600, 'business', true),
            'image_alt' => $this->faker->optional()->sentence,
            'url'       => $this->faker->optional()->url,
            'show_in'   => $this->faker->randomElement(PartnerShowInEnum::cases()),
            'order'     => $this->faker->numberBetween(0, 100),
            'is_active' => $this->faker->boolean(80), // 80% chance of being true
        ];
    }
}

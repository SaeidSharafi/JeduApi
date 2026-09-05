<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\Bundle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Bundle> */
final class BundleFactory extends Factory
{
    protected $model = Bundle::class;

    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'slug'        => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'full_name'   => $name,
            'short_name'  => fake()->sentence(2),
            'description' => fake()->paragraph(),
            'status'      => PublicationStatusEnum::DRAFT,
        ];
    }
}

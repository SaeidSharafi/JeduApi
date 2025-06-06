<?php

namespace Database\Factories;

use App\Enums\PublicationStatusEnum;
use App\Models\Product;
use App\Models\Term;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'vendor_id'         => Vendor::factory(),
            'term_id'           => Term::factory(),
            'status'            => $this->faker->randomElement(PublicationStatusEnum::getAllValues()),
            'is_visible'        => $this->faker->boolean(),
            'short_description' => $this->faker->text(),
            'short_name'        => $this->faker->name(),
            'name'              => $this->faker->name(),
            'is_featured'       => $this->faker->boolean(),
            'details_json'      => [
                'color' => $this->faker->colorName(),
                'size'  => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
            ],
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ];
    }
}

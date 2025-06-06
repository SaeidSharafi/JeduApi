<?php

namespace Database\Factories;

use App\Models\ProductDeliveryOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ProductDeliveryOptionFactory extends Factory
{
    protected $model = ProductDeliveryOption::class;

    public function definition(): array
    {
        return [
            'product_id'                => $this->faker->randomNumber(),
            'name'                      => $this->faker->name(),
            'sku'                       => $this->faker->word(),
            'fulfillment_type'          => $this->faker->word(),
            'delivery_method'           => $this->faker->word(),
            'price'                     => $this->faker->randomNumber(),
            'capacity'                  => $this->faker->randomNumber(),
            'status'                    => $this->faker->word(),
            'is_prepayment_available'   => $this->faker->boolean(),
            'prepayment_amount'         => $this->faker->randomNumber(),
            'details_json'              => $this->faker->words(),
            'is_featured'               => $this->faker->boolean(),
            'featured_price'            => $this->faker->randomNumber(),
            'featured_price_start_date' => Carbon::now(),
            'featured_price_end_date'   => Carbon::now(),
            'created_at'                => Carbon::now(),
            'updated_at'                => Carbon::now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\DeliveryMethodEnum;
use App\Enums\FulfillmentTypeEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ProductDeliveryOptionFactory extends Factory
{
    protected $model = ProductDeliveryOption::class;

    public function definition(): array
    {
        return [
            'product_id'                => Product::factory(),
            'name'                      => $this->faker->name(),
            'sku'                       => $this->faker->unique()->word(),
            'fulfillment_type'          => $this->faker->randomElement(FulfillmentTypeEnum::getAllValues()),
            'delivery_method'           => $this->faker->randomElement(DeliveryMethodEnum::getAllValues()),
            'price'                     => $this->faker->randomNumber(),
            'capacity'                  => $this->faker->randomNumber(),
            'status'                    => $this->faker->randomElement(PublicationStatusEnum::getAllValues()),
            'is_prepayment_available'   => $this->faker->boolean(),
            'prepayment_amount'         => $this->faker->randomNumber(),
            'details_json'              => [],
            'is_featured'               => $this->faker->boolean(),
            'featured_price'            => $this->faker->randomNumber(),
            'featured_price_start_date' => Carbon::now(),
            'featured_price_end_date'   => Carbon::now(),
            'created_at'                => Carbon::now(),
            'updated_at'                => Carbon::now(),
        ];
    }
}

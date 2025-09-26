<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class ProductDeliveryOptionFactory extends Factory
{
    protected $model = ProductDeliveryOption::class;

    public function definition(): array
    {
        $ftype = $this->faker->randomElement([
            FulfillmentTypeEnum::DIGITAL,
            FulfillmentTypeEnum::ONLINE_SERVICE,
            FulfillmentTypeEnum::IN_PERSON_SERVICE,
            FulfillmentTypeEnum::OFFLINE_SERVICE,
        ]);
        $pdoType = $this->faker->randomElement($ftype->getDeliveryMethods());
        $prices  = [
            100000,
            200000,
            300000,
            400000,
            500000,
            600000,
            700000,
            800000,
            900000,
            1000000,
        ];
        $price               = $this->faker->randomElement($prices);
        $prepaymentAmount    = (int) floor($this->faker->randomElement($prices) * (random_int(10, 30) / 100));
        $prepaymentAvailable = $this->faker->boolean();

        return [
            'product_id'                => Product::factory(),
            'name'                      => $this->faker->name(),
            'sku'                       => $ftype->value.$this->faker->unique()->randomNumber(),
            'fulfillment_type'          => $ftype,
            'delivery_method'           => $pdoType,
            'price'                     => $price,
            'capacity'                  => random_int(1, 100),
            'status'                    => PublicationStatusEnum::PUBLISHED->value,
            'is_prepayment_available'   => $prepaymentAvailable,
            'prepayment_amount'         => $prepaymentAvailable ? $prepaymentAmount : 0,
            'details_json'              => [],
            'is_featured'               => false,
            'featured_price'            => 0,
            'featured_price_start_date' => null,
            'featured_price_end_date'   => null,
            'created_at'                => Carbon::now(),
            'updated_at'                => Carbon::now(),
        ];
    }

    public function withTeachers(int $maxTeachers = 3, $fixedAmount = false): static
    {
        return $this->afterCreating(function (ProductDeliveryOption $deliveryOption,) use ($maxTeachers, $fixedAmount) {
            if (Teacher::count() < 10) {
                Teacher::factory(15)->create();
            }
            $teachers = Teacher::query()
                ->inRandomOrder()
                ->take($fixedAmount ? $maxTeachers : rand(1, $maxTeachers))
                ->pluck('id');
            $deliveryOption->teachers()->attach($teachers->toArray());
        });
    }
}

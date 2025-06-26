<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FulfillmentTypeEnum;
use App\Enums\PublicationStatusEnum;
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
            FulfillmentTypeEnum::OFFILNE_SERVICE,
        ]);
        $pdoType = $this->faker->randomElement($ftype->getDelivieryMethods());

        return [
            'product_id'                => Product::factory(),
            'name'                      => $this->faker->name(),
            'sku'                       => $ftype->value.$this->faker->unique()->randomNumber(),
            'fulfillment_type'          => $ftype,
            'delivery_method'           => $pdoType,
            'price'                     => $this->faker->randomNumber(),
            'capacity'                  => $this->faker->randomNumber(),
            'status'                    => $this->faker->randomElement(PublicationStatusEnum::getAllValues()),
            'is_prepayment_available'   => $this->faker->boolean(),
            'prepayment_amount'         => $this->faker->randomNumber(),
            'details_json'              => [],
            'is_featured'               => $this->faker->boolean(),
            'featured_price'            => $this->faker->randomNumber(),
            'featured_price_start_date' => Carbon::now(),
            'featured_price_end_date'   => Carbon::now()->addDay(),
            'created_at'                => Carbon::now(),
            'updated_at'                => Carbon::now(),
        ];
    }

    public function withTeachers(int $maxTeachers = 3, $fixedAmount = false): static
    {
        return $this->afterCreating(function (ProductDeliveryOption $deliveryOption, $fixedAmount) use ($maxTeachers) {
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

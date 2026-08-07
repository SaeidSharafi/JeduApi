<?php

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use function Pest\Laravel\getJson;

it('return list of paginted seminars for authenticated teacher', function (): void {

    $this->customer();

    $teacher = Teacher::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

   $teahcerPdos =  ProductDeliveryOption::factory()
        ->count(5)
        ->for(Product::factory()->withSeminar())
        ->afterCreating(function (ProductDeliveryOption $deliveryOption) use ($teacher): void {
            $deliveryOption->teachers()->attach($teacher);
        })
        ->create(
            [
                'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method' => array_rand(array_flip(DeliveryMethodEnum::getSeminars(true))),
            ]
        );
   $otherPdos =  ProductDeliveryOption::factory()
        ->count(5)
        ->for(Product::factory()->withSeminar())
        ->create();

    $response = getJson('/api/v1/shop/teacher/seminars');
    $response->assertSuccessful();

    $response->assertJsonStructure([
        'data' => [
            'data' => [
                '*' => [
                    'uuid',
                    'name',
                    'short_name',
                    'description',
                ],
            ]
        ],
    ]);
    $response->assertJsonFragment([
        'uuid' => $teahcerPdos->first()->uuid,
    ]);
    $response->assertJsonMissing([
        'uuid' => $otherPdos->first()->uuid,
    ]);
    expect($response->status())->toBe(200)
        ->and($response->json('data.data'))->toHaveCount(5);
});

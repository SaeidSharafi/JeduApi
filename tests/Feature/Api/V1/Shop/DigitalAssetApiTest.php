<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Product;
use Tests\Support\Traits\ProductTestTrait;

describe('Digital Asset API', function (): void {
    uses(ProductTestTrait::class);
    it('get a specific digital asset by slug', function (): void {
        $product = Product::factory()
            ->withDeliveryOptions(realData: [
                [
                    'fulfillment_type' => FulfillmentTypeEnum::DIGITAL,
                    'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD,
                    'price'            => 1000000,
                ],
                [
                    'fulfillment_type' => FulfillmentTypeEnum::DIGITAL,
                    'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD,
                    'price'            => 3000000,
                ],
            ])
            ->withDigitalAsset()
            ->create();

        $response = $this->getJson(route('api.v1.shop.digital-assets.show', ['product' => $product->slug]));

        $response->assertStatus(200);

        $responseData = $response->json('data');
        expect($responseData['slug'])->toBe($product->slug)
            ->and($responseData['full_name'])->toBe($product->name)
            ->and($responseData['excerpt'])->toBe($product->short_description)
            ->and(count($responseData['delivery_options']))->toBe(2)
            ->and(collect($responseData['delivery_options'])->pluck('price_data.current_price')->sort()->values()->all())
            ->toBe([1000000, 3000000]);
    });

    it('returns delivery option registration dates as Jalali when set', function (): void {
        $registrationStart = now()->subDay()->startOfDay();
        $registrationEnd   = now()->addDays(10)->startOfDay();
        $availableFrom     = now()->subDays(2)->startOfDay();

        $product = Product::factory()
            ->withDeliveryOptions(realData: [
                [
                    'fulfillment_type'        => FulfillmentTypeEnum::DIGITAL,
                    'delivery_method'         => DeliveryMethodEnum::DIRECT_DOWNLOAD,
                    'price'                   => 1000000,
                    'available_from'          => $availableFrom->toDateString(),
                    'registration_start_date' => $registrationStart->toDateString(),
                    'registration_end_date'   => $registrationEnd->toDateString(),
                ],
            ])
            ->withDigitalAsset()
            ->create();

        $response = $this->getJson(route('api.v1.shop.digital-assets.show', ['product' => $product->slug]));

        $response->assertStatus(200);

        $deliveryOption = $response->json('data.delivery_options.0');

        expect($deliveryOption['registration_start_date'])->toBe(verta($registrationStart)->format('Y-m-d H:i:s'))
            ->and($deliveryOption['registration_end_date'])->toBe(verta($registrationEnd)->format('Y-m-d H:i:s'))
            ->and($deliveryOption['available_from'])->toBe(verta($availableFrom)->format('Y-m-d H:i:s'))
            ->and($deliveryOption['is_available'])->toBeTrue()
            ->and($deliveryOption['is_purchasable'])->toBeTrue();
    });
});

<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Product;
use Tests\Traits\ProductTestTrait;

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
            ->and(count($responseData['delivery_options']))->toBe(2)
            ->and($responseData['delivery_options'][0]['price_data']['current_price'])->toBe(1000000)
            ->and($responseData['delivery_options'][1]['price_data']['current_price'])->toBe(3000000);
    });
});

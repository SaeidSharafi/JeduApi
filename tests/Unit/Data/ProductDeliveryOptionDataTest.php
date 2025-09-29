<?php

declare(strict_types=1);

describe('ProductDeliveryOptionData', function () {
    it('can be created from a model', function () {
        $deliveryOption = new App\Models\ProductDeliveryOption([
            'sku'              => 'SKU123',
            'name'             => 'Test Delivery Option',
            'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::DIGITAL,
            'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
        ]);
        $deliveryOption->uuid = '123e4567-e89b-12d3-a456-426614174000';
        $priceData            = new App\Data\Shop\ProductDeliveryOptionPriceData(
            current_price: 800,
            original_price: 1000,
            pre_payment_price: null,
            featured_price: null,
            discount_amount: 200,
            has_pre_payment_price: false,
            has_featured_price: false,
            has_discount: false,
            discount_type: 'promotion',
            discount_percentage: 20.0,
            range: ['min' => 800, 'max' => 1000],
            uuid: $deliveryOption->uuid
        );

        $data = App\Data\Shop\Product\ProductDeliveryOptionData::fromModel($deliveryOption, $priceData);

        expect($data->uuid)->toBe('123e4567-e89b-12d3-a456-426614174000')
            ->and($data->sku)->toBe('SKU123')
            ->and($data->name)->toBe('Test Delivery Option')
            ->and($data->price_data)->toBeInstanceOf(App\Data\Shop\ProductDeliveryOptionPriceData::class)
            ->and($data->fulfillment_type)->toBe(App\Enums\Product\FulfillmentTypeEnum::DIGITAL)
            ->and($data->delivery_method)->toBe(App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER);
    });
});

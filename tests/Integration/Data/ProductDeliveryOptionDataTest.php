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

    it('marks as available when only available_to is set and in the future', function (): void {
        $deliveryOption = new App\Models\ProductDeliveryOption([
            'sku'              => 'SKU456',
            'name'             => 'Future Available Option',
            'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::DIGITAL,
            'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
            'available_from'   => null,
            'available_to'     => now()->addDays(30),
        ]);
        $deliveryOption->uuid = '123e4567-e89b-12d3-a456-426614174001';

        $priceData = new App\Data\Shop\ProductDeliveryOptionPriceData(
            current_price: 500,
            original_price: 500,
            pre_payment_price: null,
            featured_price: null,
            discount_amount: 0,
            has_pre_payment_price: false,
            has_featured_price: false,
            has_discount: false,
            discount_type: 'promotion',
            discount_percentage: 0.0,
            range: ['min' => 500, 'max' => 500],
            uuid: $deliveryOption->uuid
        );

        $data = App\Data\Shop\Product\ProductDeliveryOptionData::fromModel($deliveryOption, $priceData);

        expect($data->is_available)->toBeTrue()
            ->and($data->is_purchasable)->toBeTrue();
    });

    it('marks as unavailable when only available_to is set and in the past', function (): void {
        $deliveryOption = new App\Models\ProductDeliveryOption([
            'sku'              => 'SKU789',
            'name'             => 'Expired Option',
            'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::DIGITAL,
            'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
            'available_from'   => null,
            'available_to'     => now()->subDays(1),
        ]);
        $deliveryOption->uuid = '123e4567-e89b-12d3-a456-426614174002';

        $priceData = new App\Data\Shop\ProductDeliveryOptionPriceData(
            current_price: 500,
            original_price: 500,
            pre_payment_price: null,
            featured_price: null,
            discount_amount: 0,
            has_pre_payment_price: false,
            has_featured_price: false,
            has_discount: false,
            discount_type: 'promotion',
            discount_percentage: 0.0,
            range: ['min' => 500, 'max' => 500],
            uuid: $deliveryOption->uuid
        );

        $data = App\Data\Shop\Product\ProductDeliveryOptionData::fromModel($deliveryOption, $priceData);

        expect($data->is_available)->toBeFalse();
    });

    it('marks as unavailable when only available_from is set and in the future', function (): void {
        $deliveryOption = new App\Models\ProductDeliveryOption([
            'sku'              => 'SKU010',
            'name'             => 'Future Start Option',
            'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::DIGITAL,
            'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
            'available_from'   => now()->addDays(30),
            'available_to'     => null,
        ]);
        $deliveryOption->uuid = '123e4567-e89b-12d3-a456-426614174003';

        $priceData = new App\Data\Shop\ProductDeliveryOptionPriceData(
            current_price: 500,
            original_price: 500,
            pre_payment_price: null,
            featured_price: null,
            discount_amount: 0,
            has_pre_payment_price: false,
            has_featured_price: false,
            has_discount: false,
            discount_type: 'promotion',
            discount_percentage: 0.0,
            range: ['min' => 500, 'max' => 500],
            uuid: $deliveryOption->uuid
        );

        $data = App\Data\Shop\Product\ProductDeliveryOptionData::fromModel($deliveryOption, $priceData);

        expect($data->is_available)->toBeFalse();
    });

    it('marks as available when only available_from is set and in the past', function (): void {
        $deliveryOption = new App\Models\ProductDeliveryOption([
            'sku'              => 'SKU020',
            'name'             => 'Past Start Option',
            'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::DIGITAL,
            'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
            'available_from'   => now()->subDays(30),
            'available_to'     => null,
        ]);
        $deliveryOption->uuid = '123e4567-e89b-12d3-a456-426614174004';

        $priceData = new App\Data\Shop\ProductDeliveryOptionPriceData(
            current_price: 500,
            original_price: 500,
            pre_payment_price: null,
            featured_price: null,
            discount_amount: 0,
            has_pre_payment_price: false,
            has_featured_price: false,
            has_discount: false,
            discount_type: 'promotion',
            discount_percentage: 0.0,
            range: ['min' => 500, 'max' => 500],
            uuid: $deliveryOption->uuid
        );

        $data = App\Data\Shop\Product\ProductDeliveryOptionData::fromModel($deliveryOption, $priceData);

        expect($data->is_available)->toBeTrue();
    });

    it('marks as available when both dates are set and now is between them', function (): void {
        $deliveryOption = new App\Models\ProductDeliveryOption([
            'sku'              => 'SKU030',
            'name'             => 'Active Range Option',
            'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::DIGITAL,
            'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
            'available_from'   => now()->subDays(30),
            'available_to'     => now()->addDays(30),
        ]);
        $deliveryOption->uuid = '123e4567-e89b-12d3-a456-426614174005';

        $priceData = new App\Data\Shop\ProductDeliveryOptionPriceData(
            current_price: 500,
            original_price: 500,
            pre_payment_price: null,
            featured_price: null,
            discount_amount: 0,
            has_pre_payment_price: false,
            has_featured_price: false,
            has_discount: false,
            discount_type: 'promotion',
            discount_percentage: 0.0,
            range: ['min' => 500, 'max' => 500],
            uuid: $deliveryOption->uuid
        );

        $data = App\Data\Shop\Product\ProductDeliveryOptionData::fromModel($deliveryOption, $priceData);

        expect($data->is_available)->toBeTrue()
            ->and($data->is_purchasable)->toBeTrue();
    });

    it('marks as unavailable when both dates are set and now is outside range', function (): void {
        $deliveryOption = new App\Models\ProductDeliveryOption([
            'sku'              => 'SKU040',
            'name'             => 'Expired Range Option',
            'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::DIGITAL,
            'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
            'available_from'   => now()->subDays(60),
            'available_to'     => now()->subDays(30),
        ]);
        $deliveryOption->uuid = '123e4567-e89b-12d3-a456-426614174006';

        $priceData = new App\Data\Shop\ProductDeliveryOptionPriceData(
            current_price: 500,
            original_price: 500,
            pre_payment_price: null,
            featured_price: null,
            discount_amount: 0,
            has_pre_payment_price: false,
            has_featured_price: false,
            has_discount: false,
            discount_type: 'promotion',
            discount_percentage: 0.0,
            range: ['min' => 500, 'max' => 500],
            uuid: $deliveryOption->uuid
        );

        $data = App\Data\Shop\Product\ProductDeliveryOptionData::fromModel($deliveryOption, $priceData);

        expect($data->is_available)->toBeFalse()
            ->and($data->is_purchasable)->toBeFalse();
    });
});

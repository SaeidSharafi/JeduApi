<?php

declare(strict_types=1);

it('get delivery methods correctl for DIGITAL', function (): void {
    $fulfillmentType = App\Enums\Product\FulfillmentTypeEnum::DIGITAL;

    $deliveryMethods = $fulfillmentType->getDeliveryMethods();

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(1)
        ->toContain(App\Enums\Product\DeliveryMethodEnum::DIRECT_DOWNLOAD);
});

it('get delivery methods correctly for PHYSICAL', function (): void {
    $fulfillmentType = App\Enums\Product\FulfillmentTypeEnum::PHYSICAL;

    $deliveryMethods = $fulfillmentType->getDeliveryMethods();

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(0);
});
it('get delivery methods correctly for ONLINE_SERVICE', function (): void {
    $fulfillmentType = App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE;

    $deliveryMethods = $fulfillmentType->getDeliveryMethods();

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(3)
        ->toContain(App\Enums\Product\DeliveryMethodEnum::LIVE_SESSION_BBB)
        ->toContain(App\Enums\Product\DeliveryMethodEnum::LIVE_SESSION_SKYROOM)
        ->toContain(App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE);
});
it('get delivery methods correctly for OFFLINE_SERVICE', function (): void {
    $fulfillmentType = App\Enums\Product\FulfillmentTypeEnum::OFFLINE_SERVICE;

    $deliveryMethods = $fulfillmentType->getDeliveryMethods();

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(1)
        ->toContain(App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER);
});

it('get delivery methods correctly for IN_PERSON_SERVICE', function (): void {
    $fulfillmentType = App\Enums\Product\FulfillmentTypeEnum::IN_PERSON_SERVICE;

    $deliveryMethods = $fulfillmentType->getDeliveryMethods();

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(1)
        ->toContain(App\Enums\Product\DeliveryMethodEnum::IN_PERSON);
});

it('get delivery methods for unknown fulfillment type returns empty array', function (): void {
    $fulfillmentType = 'unknown_fulfillment_type';

    $deliveryMethods = App\Enums\Product\FulfillmentTypeEnum::getDeliveryMethodsFor($fulfillmentType);

    expect($deliveryMethods)->toBeArray()->toHaveCount(0);
});

it('get delivery methods for known fulfillment type returns correct methods', function (): void {
    $fulfillmentType = App\Enums\Product\FulfillmentTypeEnum::DIGITAL->value;

    $deliveryMethods = App\Enums\Product\FulfillmentTypeEnum::getDeliveryMethodsFor($fulfillmentType);

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(1)
        ->toContain(App\Enums\Product\DeliveryMethodEnum::DIRECT_DOWNLOAD);
});

it('get delivery methods for invalid fulfillment type returns empty array', function (): void {
    $fulfillmentType = 'invalid_fulfillment_type';

    $deliveryMethods = App\Enums\Product\FulfillmentTypeEnum::getDeliveryMethodsFor($fulfillmentType);

    expect($deliveryMethods)->toBeArray()->toHaveCount(0);
});

it('get delivery methods for empty fulfillment type returns empty array', function (): void {
    $fulfillmentType = '';

    $deliveryMethods = App\Enums\Product\FulfillmentTypeEnum::getDeliveryMethodsFor($fulfillmentType);

    expect($deliveryMethods)->toBeArray()->toHaveCount(0);
});

it('get delivery methods for fulfillment type with no methods returns empty array', function (): void {
    $fulfillmentType = App\Enums\Product\FulfillmentTypeEnum::PHYSICAL->value;

    $deliveryMethods = App\Enums\Product\FulfillmentTypeEnum::getDeliveryMethodsFor($fulfillmentType);

    expect($deliveryMethods)->toBeArray()->toHaveCount(0);
});

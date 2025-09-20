<?php

declare(strict_types=1);

it('get delivery methods correctl for DIGITAL', function (): void {
    $fulfillmentType = App\Enums\FulfillmentTypeEnum::DIGITAL;

    $deliveryMethods = $fulfillmentType->getDeliveryMethods();

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(1)
        ->toContain(App\Enums\DeliveryMethodEnum::DIRECT_DOWNLOAD);
});

it('get delivery methods correctly for PHYSICAL', function (): void {
    $fulfillmentType = App\Enums\FulfillmentTypeEnum::PHYSICAL;

    $deliveryMethods = $fulfillmentType->getDeliveryMethods();

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(0);
});
it('get delivery methods correctly for ONLINE_SERVICE', function (): void {
    $fulfillmentType = App\Enums\FulfillmentTypeEnum::ONLINE_SERVICE;

    $deliveryMethods = $fulfillmentType->getDeliveryMethods();

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(3)
        ->toContain(App\Enums\DeliveryMethodEnum::LIVE_SESSION_BBB)
        ->toContain(App\Enums\DeliveryMethodEnum::LIVE_SESSION_SKYROOM)
        ->toContain(App\Enums\DeliveryMethodEnum::LMS_MOODLE);
});
it('get delivery methods correctly for OFFLINE_SERVICE', function (): void {
    $fulfillmentType = App\Enums\FulfillmentTypeEnum::OFFLINE_SERVICE;

    $deliveryMethods = $fulfillmentType->getDeliveryMethods();

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(1)
        ->toContain(App\Enums\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER);
});

it('get delivery methods correctly for IN_PERSON_SERVICE', function (): void {
    $fulfillmentType = App\Enums\FulfillmentTypeEnum::IN_PERSON_SERVICE;

    $deliveryMethods = $fulfillmentType->getDeliveryMethods();

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(1)
        ->toContain(App\Enums\DeliveryMethodEnum::IN_PERSON);
});

it('get delivery methods for unknown fulfillment type returns empty array', function (): void {
    $fulfillmentType = 'unknown_fulfillment_type';

    $deliveryMethods = App\Enums\FulfillmentTypeEnum::getDeliveryMethodsFor($fulfillmentType);

    expect($deliveryMethods)->toBeArray()->toHaveCount(0);
});

it('get delivery methods for known fulfillment type returns correct methods', function (): void {
    $fulfillmentType = App\Enums\FulfillmentTypeEnum::DIGITAL->value;

    $deliveryMethods = App\Enums\FulfillmentTypeEnum::getDeliveryMethodsFor($fulfillmentType);

    expect($deliveryMethods)
        ->toBeArray()
        ->toHaveCount(1)
        ->toContain(App\Enums\DeliveryMethodEnum::DIRECT_DOWNLOAD);
});

it('get delivery methods for invalid fulfillment type returns empty array', function (): void {
    $fulfillmentType = 'invalid_fulfillment_type';

    $deliveryMethods = App\Enums\FulfillmentTypeEnum::getDeliveryMethodsFor($fulfillmentType);

    expect($deliveryMethods)->toBeArray()->toHaveCount(0);
});

it('get delivery methods for empty fulfillment type returns empty array', function (): void {
    $fulfillmentType = '';

    $deliveryMethods = App\Enums\FulfillmentTypeEnum::getDeliveryMethodsFor($fulfillmentType);

    expect($deliveryMethods)->toBeArray()->toHaveCount(0);
});

it('get delivery methods for fulfillment type with no methods returns empty array', function (): void {
    $fulfillmentType = App\Enums\FulfillmentTypeEnum::PHYSICAL->value;

    $deliveryMethods = App\Enums\FulfillmentTypeEnum::getDeliveryMethodsFor($fulfillmentType);

    expect($deliveryMethods)->toBeArray()->toHaveCount(0);
});

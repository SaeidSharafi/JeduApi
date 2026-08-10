<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('returns each fulfillment with its compatible delivery options', function (): void {
    $this->authorized_user();

    $response = $this->getJson(route('api.v1.admin.select-option.delivery-options'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'value',
                    'label',
                    'delivery_options' => [
                        '*' => [
                            'value',
                            'label',
                        ],
                    ],
                ],
            ],
        ]);

    foreach (FulfillmentTypeEnum::cases() as $fulfillmentType) {
        $response->assertJsonFragment([
            'value' => $fulfillmentType->value,
            'label' => $fulfillmentType->translate(),
        ]);
    }

    $onlineService = collect($response->json('data'))
        ->firstWhere('value', FulfillmentTypeEnum::ONLINE_SERVICE->value);

    expect($onlineService['delivery_options'])->toEqual([
        [
            'value' => DeliveryMethodEnum::LIVE_SESSION_BBB->value,
            'label' => DeliveryMethodEnum::LIVE_SESSION_BBB->translate(),
        ],
        [
            'value' => DeliveryMethodEnum::LIVE_SESSION_SKYROOM->value,
            'label' => DeliveryMethodEnum::LIVE_SESSION_SKYROOM->translate(),
        ],
        [
            'value' => DeliveryMethodEnum::LMS_MOODLE->value,
            'label' => DeliveryMethodEnum::LMS_MOODLE->translate(),
        ],
    ]);
});

it('requires staff authentication', function (): void {
    $this->getJson(route('api.v1.admin.select-option.delivery-options'))
        ->assertUnauthorized();
});

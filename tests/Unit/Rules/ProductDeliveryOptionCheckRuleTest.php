<?php

declare(strict_types=1);

use App\Enums\DeliveryMethodEnum;

it('passes if the delivery method belongs to fulfillment type', function (): void {
    $rule      = new App\Rules\ProductDeliveryOptionCheckRule();
    $course    = App\Models\Course::factory()->create()->fresh();
    $validator = Validator::make(
        [
            'fulfillment_type' => App\Enums\FulfillmentTypeEnum::DIGITAL->value,
            'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
        ],
        [
            'delivery_method' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();

});
it('fails if the delivery method does not belongs to fulfillment type', function (): void {
    $rule      = new App\Rules\ProductDeliveryOptionCheckRule();
    $course    = App\Models\Course::factory()->create()->fresh();
    $validator = Validator::make(
        [
            'fulfillment_type' => App\Enums\FulfillmentTypeEnum::DIGITAL->value,
            'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE->value,
        ],
        [
            'delivery_method' => [$rule],
        ]
    );
    expect($validator->fails())->toBeTrue();
});
it('ignore (passes) if fulfillment type is empty', function (): void {
    $rule      = new App\Rules\ProductDeliveryOptionCheckRule();
    $course    = App\Models\Course::factory()->create()->fresh();
    $validator = Validator::make(
        [
            'fulfillment_type' => null,
            'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE->value,
        ],
        [
            'delivery_method' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();
});

it('ignore (passes) if delivery method is null', function (): void {
    $rule      = new App\Rules\ProductDeliveryOptionCheckRule();
    $course    = App\Models\Course::factory()->create()->fresh();
    $validator = Validator::make(
        [
            'fulfillment_type' => App\Enums\FulfillmentTypeEnum::PHYSICAL->value,
            'delivery_method'  => null,
        ],
        [
            'delivery_method' => [$rule],
        ]
    );
    expect($validator->passes())->toBeTrue();
});

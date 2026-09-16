<?php

declare(strict_types=1);

use App\Data\Shop\Product\Bundle\BundleComponentData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;

covers(BundleComponentData::class);

it('presents each delivery method by its provider while keeping the persisted method value', function (
    DeliveryMethodEnum $deliveryMethod,
    string $expectedPresentation,
): void {
    $option = ProductDeliveryOption::factory()->create([
        'product_id'       => Product::factory()->withCourse()->create()->id,
        'delivery_method'  => $deliveryMethod,
        'fulfillment_type' => $deliveryMethod->getFulfillmentType(),
        'details_json'     => [],
    ]);

    $component = BundleComponentData::fromModel($option->fresh(), 1000, true);

    expect($component->provider_presentation)->toBe($expectedPresentation)
        ->and($component->delivery_method)->toBe($deliveryMethod->value)
        // An option with no dates and no teachers reports nulls rather than fabricated values.
        ->and($component->available_from)->toBeNull()
        ->and($component->available_to)->toBeNull()
        ->and($component->registration_start_date)->toBeNull()
        ->and($component->registration_end_date)->toBeNull()
        ->and($component->teachers)->toBeEmpty();
})->with([
    'moodle'     => [DeliveryMethodEnum::LMS_MOODLE, 'Moodle'],
    'spotplayer' => [DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER, 'SpotPlayer'],
    'download'   => [DeliveryMethodEnum::DIRECT_DOWNLOAD, 'Digital download'],
    'niliroom'   => [DeliveryMethodEnum::LIVE_SESSION_NILIROOM, 'Niliroom'],
    'skyroom'    => [DeliveryMethodEnum::LIVE_SESSION_SKYROOM, 'Skyroom'],
    'in person'  => [DeliveryMethodEnum::IN_PERSON, 'In person'],
    'unmapped'   => [DeliveryMethodEnum::BUNDLE, 'Component fulfillment'],
]);

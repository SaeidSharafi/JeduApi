<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\ProductReservationService;
use Illuminate\Validation\ValidationException;

mutates(ProductReservationService::class);

it('increments reserved_count on reserve', function (): void {
    $option = ProductDeliveryOption::factory()->create(['reserved_count' => 0]);

    app(ProductReservationService::class)->reserve($option->id, 3);

    expect($option->fresh()->reserved_count)->toBe(3);
});

it('rejects a bundle reservation when the bundle has no component options', function (): void {
    $bundle  = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->create([
        'productable_type' => 'bundle',
        'productable_id'   => $bundle->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);
    $option = ProductDeliveryOption::factory()->create([
        'product_id'       => $product->id,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
        'capacity'         => null,
        'status'           => PublicationStatusEnum::PUBLISHED,
    ]);

    expect(fn () => app(ProductReservationService::class)->reserve($option->id, 1))
        ->toThrow(ValidationException::class, __('messages.product.bundle_components_required'));
});

it('rejects a reservation when a bundle component would exceed capacity', function (): void {
    $bundle  = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->create([
        'productable_type' => 'bundle',
        'productable_id'   => $bundle->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);
    $bundleOption = ProductDeliveryOption::factory()->create([
        'product_id'       => $product->id,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
        'capacity'         => null,
        'status'           => PublicationStatusEnum::PUBLISHED,
    ]);
    $course           = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $componentProduct = Product::factory()->withCourse($course)->create([
        'status'     => PublicationStatusEnum::PUBLISHED,
        'is_visible' => true,
    ]);
    $component = ProductDeliveryOption::factory()->create([
        'product_id'     => $componentProduct->id,
        'capacity'       => 3,
        'enrolled_count' => 2,
        'reserved_count' => 0,
        'status'         => PublicationStatusEnum::PUBLISHED,
    ]);
    $bundleOption->bundleComponents()->attach($component->id, ['allocation' => 100000]);

    expect(fn () => app(ProductReservationService::class)->reserve($bundleOption->id, 2))
        ->toThrow(ValidationException::class, __('messages.product.bundle_component_capacity_exceeded'));
});

it('consumes a reservation on payment completion', function (): void {
    $option = ProductDeliveryOption::factory()->create(['reserved_count' => 5]);

    app(ProductReservationService::class)->consume($option->id, 2);

    expect($option->fresh()->reserved_count)->toBe(3);
});

it('releases a reservation on cancellation', function (): void {
    $option = ProductDeliveryOption::factory()->create(['reserved_count' => 4]);

    app(ProductReservationService::class)->release($option->id, 4);

    expect($option->fresh()->reserved_count)->toBe(0);
});

it('never decrements reserved_count below zero', function (): void {
    $option = ProductDeliveryOption::factory()->create(['reserved_count' => 1]);

    app(ProductReservationService::class)->release($option->id, 10);

    expect($option->fresh()->reserved_count)->toBe(0);
});

it('accumulates multiple reserves', function (): void {
    $option  = ProductDeliveryOption::factory()->create(['reserved_count' => 0]);
    $service = app(ProductReservationService::class);

    $service->reserve($option->id, 2);
    $service->reserve($option->id, 3);

    expect($option->fresh()->reserved_count)->toBe(5);
});

it('decrements reservations for every bundle component during release', function (): void {
    $bundle  = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->create([
        'productable_type' => 'bundle',
        'productable_id'   => $bundle->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);
    $parent = ProductDeliveryOption::factory()->create([
        'product_id'       => $product->id,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
        'capacity'         => null,
        'status'           => PublicationStatusEnum::PUBLISHED,
    ]);
    $course           = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $componentProduct = Product::factory()->withCourse($course)->create([
        'status'     => PublicationStatusEnum::PUBLISHED,
        'is_visible' => true,
    ]);
    $component = ProductDeliveryOption::factory()->create([
        'product_id'     => $componentProduct->id,
        'capacity'       => null,
        'enrolled_count' => 0,
        'reserved_count' => 5,
        'status'         => PublicationStatusEnum::PUBLISHED,
    ]);
    $parent->bundleComponents()->attach($component->id, ['allocation' => 100000]);

    app(ProductReservationService::class)->release($parent->id, 3);

    expect($component->fresh()->reserved_count)->toBe(2);
});

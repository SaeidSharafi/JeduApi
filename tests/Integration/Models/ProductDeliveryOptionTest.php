<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;

test('to array', function (): void {
    $productDeliveryOption = App\Models\ProductDeliveryOption::factory()->create()->fresh();

    expect($productDeliveryOption->toArray())
        ->toEqual([
            'id'                        => $productDeliveryOption->id,
            'uuid'                      => $productDeliveryOption->uuid,
            'product_id'                => $productDeliveryOption->product_id,
            'name'                      => $productDeliveryOption->name,
            'sku'                       => $productDeliveryOption->sku,
            'fulfillment_type'          => $productDeliveryOption->fulfillment_type->value,
            'delivery_method'           => $productDeliveryOption->delivery_method->value,
            'price'                     => $productDeliveryOption->price,
            'capacity'                  => $productDeliveryOption->capacity,
            'enrolled_count'            => $productDeliveryOption->enrolled_count,
            'status'                    => $productDeliveryOption->status->value,
            'is_prepayment_available'   => $productDeliveryOption->is_prepayment_available,
            'prepayment_amount'         => $productDeliveryOption->prepayment_amount,
            'details_json'              => $productDeliveryOption->details_json,
            'is_featured'               => $productDeliveryOption->is_featured,
            'featured_price'            => $productDeliveryOption->featured_price,
            'featured_price_start_date' => $productDeliveryOption->featured_price_start_date?->utc()
                ->toJSON(),
            'featured_price_end_date' => $productDeliveryOption->featured_price_end_date?->utc()
                ->toJSON(),
            'registration_start_date'                => $productDeliveryOption->registration_start_date?->format('Y-m-d'),
            'registration_end_date'                  => $productDeliveryOption->registration_end_date?->format('Y-m-d'),
            'available_from'                         => $productDeliveryOption->available_from?->format('Y-m-d'),
            'available_to'                           => $productDeliveryOption->available_to?->format('Y-m-d'),
            'created_at'                             => $productDeliveryOption->created_at?->utc()?->toJSON(),
            'updated_at'                             => $productDeliveryOption->updated_at?->utc()?->toJSON(),
            'allow_multiple_quantity'                => $productDeliveryOption->allow_multiple_quantity,
            'product_delivery_option_discount_price' => $productDeliveryOption->productDeliveryOptionDiscountPrice,
        ]);

});

test('relation products', function (): void {
    $product        = App\Models\Product::factory()->create();
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create(['product_id' => $product->id]);
    expect($deliveryOption->product)
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($deliveryOption->product->id)
        ->toEqual($product->id);
});

test('relation teachers', function (): void {
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create();
    $teachers       = App\Models\Teacher::factory()->count(3)->create();
    $deliveryOption->teachers()->attach($teachers->pluck('id'));
    $deliveryOption->load('teachers');
    expect($deliveryOption->teachers)
        ->toHaveCount(3)
        ->and($deliveryOption->teachers->first())
        ->toBeInstanceOf(App\Models\Teacher::class);
});

test('relation discount prices', function (): void {

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create();
    $discountPrice  = App\Models\ProductDeliveryOptionDiscountPrice::create([
        'product_delivery_option_id' => $deliveryOption->id,
        'discount_promotion_id'      => App\Models\DiscountPromotion::factory()->create()->id,
        'discounted_price'           => 5000,
    ]);
    $deliveryOption->load('productDeliveryOptionDiscountPrice');
    expect($deliveryOption->productDeliveryOptionDiscountPrice)
        ->toBeInstanceOf(App\Models\ProductDeliveryOptionDiscountPrice::class)
        ->and($deliveryOption->productDeliveryOptionDiscountPrice->discounted_price)
        ->toEqual(5000);
});
test('relation enrollments', function (): void {
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create();
    $enrollments    = App\Models\Enrollment::factory()->count(2)->create([
        'product_delivery_option_id' => $deliveryOption->id,
    ]);
    $deliveryOption->load('enrollments');
    expect($deliveryOption->enrollments)
        ->toHaveCount(2)
        ->and($deliveryOption->enrollments->first())
        ->toBeInstanceOf(App\Models\Enrollment::class);
});
test('scope available', function (): void {
    $availableOption = App\Models\ProductDeliveryOption::factory()->create([
        'status'         => PublicationStatusEnum::PUBLISHED,
        'available_from' => now()->subDays(1),
        'available_to'   => now()->addDays(1),
    ])->fresh();
    $unavailableOption = App\Models\ProductDeliveryOption::factory()->create([
        'status'         => PublicationStatusEnum::DRAFT,
        'available_from' => now()->addDays(1),
        'available_to'   => now()->addDays(2),
    ])->fresh();

    $result = App\Models\ProductDeliveryOption::available()->get();

    expect($result)
        ->toHaveCount(1)
        ->and($result->first()->is($availableOption))
        ->toBeTrue();
});
test('scope featured', function (): void {
    $featuredOption = App\Models\ProductDeliveryOption::factory()->create([
        'is_featured'               => true,
        'featured_price_start_date' => now()->subDays(1),
        'featured_price_end_date'   => now()->addDays(1),
    ])->fresh();
    $nonFeaturedOption = App\Models\ProductDeliveryOption::factory()->create([
        'is_featured'               => false,
        'featured_price_start_date' => now()->addDays(1),
        'featured_price_end_date'   => now()->addDays(2),
    ])->fresh();

    $result = App\Models\ProductDeliveryOption::featured()->get();

    expect($result)
        ->toHaveCount(1)
        ->and($result->first()->is($featuredOption))
        ->toBeTrue();
});
test('scope prepayment available', function (): void {
    $prepaymentOption = App\Models\ProductDeliveryOption::factory()->create([
        'is_prepayment_available' => true,
        'prepayment_amount'       => 1000,
    ])->fresh();
    $nonPrepaymentOption = App\Models\ProductDeliveryOption::factory()->create([
        'is_prepayment_available' => false,
        'prepayment_amount'       => null,
    ])->fresh();

    $result = App\Models\ProductDeliveryOption::prepaymentAvailable()->get();

    expect($result)
        ->toHaveCount(1)
        ->and($result->first()->is($prepaymentOption))
        ->toBeTrue();
});
test('scope registration open', function (): void {
    $openRegistrationOption = App\Models\ProductDeliveryOption::factory()->create([
        'registration_start_date' => now()->subDays(1),
        'registration_end_date'   => now()->addDays(1),
    ])->fresh();
    $closedRegistrationOption = App\Models\ProductDeliveryOption::factory()->create([
        'registration_start_date' => now()->addDays(1),
        'registration_end_date'   => now()->addDays(2),
    ])->fresh();

    $result = App\Models\ProductDeliveryOption::registrationOpen()->get();

    expect($result)
        ->toHaveCount(1)
        ->and($result->first()->is($openRegistrationOption))
        ->toBeTrue();
});

test('discountPrice', function (): void {
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create(['price' => 10000]);
    expect($deliveryOption->discountPrice)->toEqual(10000);

    $discountPrice = App\Models\ProductDeliveryOptionDiscountPrice::create([
        'product_delivery_option_id' => $deliveryOption->id,
        'discount_promotion_id'      => App\Models\DiscountPromotion::factory()->create()->id,
        'discounted_price'           => 8000,
    ]);
    $deliveryOption->load('productDeliveryOptionDiscountPrice');
    expect($deliveryOption->discountPrice)->toEqual(8000);

    $discountPrice->delete();

    $deliveryOption->load('productDeliveryOptionDiscountPrice');
    expect($deliveryOption->discountPrice)->toEqual(10000);
});

describe('scopes', function () {

    it('withCapacityInfo adds enrolled_count', function (): void {
        $deliveryOption = App\Models\ProductDeliveryOption::factory()->create();
        $enrollments    = App\Models\Enrollment::factory()->count(3)->create([
            'product_delivery_option_id' => $deliveryOption->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);
        // Add a cancelled enrollment which should not be counted
        App\Models\Enrollment::factory()->create([
            'product_delivery_option_id' => $deliveryOption->id,
            'enrollment_status'          => EnrollmentStatusEnum::CANCELLED,
        ]);

        $result = App\Models\ProductDeliveryOption::withCapacityInfo()->find($deliveryOption->id);

        expect($result)
            ->not->toBeNull()
            ->and($result->enrolled_count)->toEqual(3);
    });

    it('availableWithCapacity returns only options with available spots', function (): void {
        $optionWithCapacity = App\Models\ProductDeliveryOption::factory()->create([
            'status'         => PublicationStatusEnum::PUBLISHED,
            'capacity'       => 5,
            'available_from' => now()->subDays(1),
            'available_to'   => now()->addDays(1),
        ]);
        // 3 active enrollments, capacity 5 -> should be available
        App\Models\Enrollment::factory()->count(3)->create([
            'product_delivery_option_id' => $optionWithCapacity->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);

        $optionAtCapacity = App\Models\ProductDeliveryOption::factory()->create([
            'status'         => PublicationStatusEnum::PUBLISHED,
            'capacity'       => 3,
            'available_from' => now()->subDays(1),
            'available_to'   => now()->addDays(1),
        ]);
        // 3 active enrollments, capacity 3 -> should NOT be available
        App\Models\Enrollment::factory()->count(3)->create([
            'product_delivery_option_id' => $optionAtCapacity->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);

        $optionWithoutCapacity = App\Models\ProductDeliveryOption::factory()->create([
            'status'         => PublicationStatusEnum::PUBLISHED,
            'capacity'       => null,
            'available_from' => now()->subDays(1),
            'available_to'   => now()->addDays(1),
        ]);
        // No capacity limit -> should be available
        App\Models\Enrollment::factory()->count(10)->create([
            'product_delivery_option_id' => $optionWithoutCapacity->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);
        $result = App\Models\ProductDeliveryOption::availableWithCapacity()->get();
        expect($result->pluck('id')->all())
            ->toContain($optionWithCapacity->id)
            ->and($result->pluck('id')->all())
            ->toContain($optionWithoutCapacity->id)
            ->and($result->pluck('id')->all())
            ->not->toContain($optionAtCapacity->id);
    });
});

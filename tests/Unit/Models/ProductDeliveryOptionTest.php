<?php

declare(strict_types=1);

test('to array', function (): void {
    $productDeliveryOption = App\Models\ProductDeliveryOption::factory()->create()->fresh();

    expect($productDeliveryOption->toArray())
        ->toEqual([
            'id'                        => $productDeliveryOption->id,
            'product_id'                => $productDeliveryOption->product_id,
            'name'                      => $productDeliveryOption->name,
            'sku'                       => $productDeliveryOption->sku,
            'fulfillment_type'          => $productDeliveryOption->fulfillment_type->value,
            'delivery_method'           => $productDeliveryOption->delivery_method->value,
            'price'                     => $productDeliveryOption->price,
            'capacity'                  => $productDeliveryOption->capacity,
            'status'                    => $productDeliveryOption->status->value,
            'is_prepayment_available'   => $productDeliveryOption->is_prepayment_available,
            'prepayment_amount'         => $productDeliveryOption->prepayment_amount,
            'details_json'              => $productDeliveryOption->details_json,
            'is_featured'               => $productDeliveryOption->is_featured,
            'featured_price'            => $productDeliveryOption->featured_price,
            'featured_price_start_date' => $productDeliveryOption->featured_price_start_date?->format('Y-m-d H:i:s'),
            'featured_price_end_date'   => $productDeliveryOption->featured_price_end_date?->format('Y-m-d H:i:s'),
            'registration_start_date'   => $productDeliveryOption->registration_start_date?->format('Y-m-d'),
            'registration_end_date'     => $productDeliveryOption->registration_end_date?->format('Y-m-d'),
            'available_from'            => $productDeliveryOption->available_from?->format('Y-m-d'),
            'available_to'              => $productDeliveryOption->available_to?->format('Y-m-d'),
            'created_at'                => $productDeliveryOption->created_at?->format('Y-m-d H:i:s'),
            'updated_at'                => $productDeliveryOption->updated_at?->format('Y-m-d H:i:s'),
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

test('scope available', function (): void {
    $availableOption = App\Models\ProductDeliveryOption::factory()->create([
        'status'         => App\Enums\PublicationStatusEnum::PUBLISHED,
        'available_from' => now()->subDays(1),
        'available_to'   => now()->addDays(1),
    ])->fresh();
    $unavailableOption = App\Models\ProductDeliveryOption::factory()->create([
        'status'         => App\Enums\PublicationStatusEnum::DRAFT,
        'available_from' => now()->addDays(1),
        'available_to'   => now()->addDays(2),
    ])->fresh();

    $result = App\Models\ProductDeliveryOption::available()->get();

    expect($result)
        ->toHaveCount(1)
        ->and($result->first())
        ->toEqual($availableOption);
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
        ->and($result->first())
        ->toEqual($featuredOption);
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
        ->and($result->first())
        ->toEqual($prepaymentOption);
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
        ->and($result->first())
        ->toEqual($openRegistrationOption);
});

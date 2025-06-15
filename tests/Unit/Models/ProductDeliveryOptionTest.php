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
            'created_at'               => $productDeliveryOption->created_at?->format('Y-m-d H:i:s'),
            'updated_at'               => $productDeliveryOption->updated_at?->format('Y-m-d H:i:s'),
        ]);

});

test('relation products', function (): void {
    $product = App\Models\Product::factory()->create();
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create(['product_id' => $product->id]);
    expect($deliveryOption->product)
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($deliveryOption->product->id)
        ->toEqual($product->id);
});

test('relation teachers', function (): void {
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create();
    $teachers = App\Models\Teacher::factory()->count(3)->create();
    $deliveryOption->teachers()->attach($teachers->pluck('id'));
    $deliveryOption->load('teachers');
    expect($deliveryOption->teachers)
        ->toHaveCount(3)
        ->and($deliveryOption->teachers->first())
        ->toBeInstanceOf(App\Models\Teacher::class);
});

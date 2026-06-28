<?php

declare(strict_types=1);

test('to array', function (): void {
    $product = App\Models\Product::factory()->create()->fresh();

    expect($product->toArray())
        ->toEqual([
            'id'                => $product->id,
            'vendor_id'         => $product->vendor_id,
            'productable_id'    => $product->productable_id,
            'productable_type'  => $product->productable_type,
            'term_id'           => $product->term_id,
            'status'            => $product->status->value,
            'is_visible'        => $product->is_visible,
            'short_description' => $product->short_description,
            'short_name'        => $product->short_name,
            'name'              => $product->name,
            'slug'              => $product->slug,
            'is_featured'       => $product->is_featured,
            'price_data_cache'  => $product->price_data_cache,
            'details_json'      => $product->details_json,
            'event_start_at'    => null,
            'event_ended_at'    => null,
            'created_at'        => $product->created_at?->utc()?->toJSON(),
            'updated_at'        => $product->updated_at?->utc()?->toJSON(),
        ]);

});

test('relation categories', function (): void {
    $product  = App\Models\Product::factory()->create();
    $category = App\Models\Category::factory()->create();
    $product->categories()->attach($category->id);

    expect($product->categories)
        ->toHaveCount(1)
        ->and($product->categories->first())
        ->toBeInstanceOf(App\Models\Category::class)
        ->and($product->categories->first()->id)
        ->toEqual($category->id);

    $categories = App\Models\Category::factory()->count(3)->create();
    $product->categories()->sync($categories);
    $product->refresh();
    expect($product->categories)
        ->toHaveCount(3);
});

test('relation product_delivery_options', function (): void {
    $product        = App\Models\Product::factory()->create();
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create(['product_id' => $product->id]);

    expect($product->productDeliveryOptions)
        ->toHaveCount(1)
        ->and($product->productDeliveryOptions->first())
        ->toBeInstanceOf(App\Models\ProductDeliveryOption::class)
        ->and($product->productDeliveryOptions->first()->id)
        ->toEqual($deliveryOption->id);
});

test('relation orderItems through product_delivery_options', function (): void {
    $product        = App\Models\Product::factory()->create();
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create(['product_id' => $product->id]);
    $orderItem      = App\Models\OrderItem::factory()->create(['product_delivery_option_id' => $deliveryOption->id]);

    expect($product->orderItems)
        ->toHaveCount(1)
        ->and($product->orderItems->first())
        ->toBeInstanceOf(App\Models\OrderItem::class)
        ->and($product->orderItems->first()->id)
        ->toEqual($orderItem->id);
});

test('relation relatedProducts', function (): void {
    $productA = App\Models\Product::factory()->create();
    $productB = App\Models\Product::factory()->create();

    $productA->relatedProducts()->attach($productB->id, ['relation_type' => 'related']);

    expect($productA->relatedProducts)
        ->toHaveCount(1)
        ->and($productA->relatedProducts->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($productA->relatedProducts->first()->id)
        ->toEqual($productB->id);
});

test('relation relatedProducts with type', function (): void {
    $productA = App\Models\Product::factory()->create();
    $productB = App\Models\Product::factory()->create();
    $productC = App\Models\Product::factory()->create();
    $productD = App\Models\Product::factory()->create();

    $productA->relatedProducts()->attach($productB->id, ['relation_type' => 'related']);
    $productA->relatedProducts()->attach($productC->id, ['relation_type' => 'cross_sell']);
    $productA->relatedProducts()->attach($productD->id, ['relation_type' => 'upsell']);

    expect($productA->relatedProductsOfType()->get())
        ->toHaveCount(1)
        ->and($productA->relatedProductsOfType()->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($productA->relatedProductsOfType()->first()->id)
        ->toEqual($productB->id)
        ->and($productA->crossSellProducts()->get())
        ->toHaveCount(1)
        ->and($productA->crossSellProducts()->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($productA->crossSellProducts()->first()->id)
        ->toEqual($productC->id)
        ->and($productA->upsellProducts()->get())
        ->toHaveCount(1)
        ->and($productA->upsellProducts()->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($productA->upsellProducts()->first()->id)
        ->toEqual($productD->id);

});

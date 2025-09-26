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
describe('scopes', function (): void {
    it('active', function (): void {
        $activeProduct = App\Models\Product::factory()->create([
            'status'     => \App\Enums\Content\PublicationStatusEnum::PUBLISHED,
            'is_visible' => true,
        ]);
        $inactiveProduct = App\Models\Product::factory()->create([
            'status'     => \App\Enums\Content\PublicationStatusEnum::DRAFT,
            'is_visible' => false,
        ]);

        // Ensure the productable is published
        $productable         = $activeProduct->productable;
        $productable->status = \App\Enums\Content\PublicationStatusEnum::PUBLISHED;
        $productable->save();

        // Ensure the product delivery option is published
        $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $activeProduct->id,
            'status'     => \App\Enums\Content\PublicationStatusEnum::PUBLISHED,
        ]);

        $activeProducts = App\Models\Product::active()->get();

        expect($activeProducts)
            ->toHaveCount(1)
            ->and($activeProducts->first()->id)
            ->toEqual($activeProduct->id);
    });

    it('active with relations', function (): void {
        $activeProduct = App\Models\Product::factory()->create([
            'status'     => \App\Enums\Content\PublicationStatusEnum::PUBLISHED,
            'is_visible' => true,
        ]);
        $inactiveProduct = App\Models\Product::factory()->create([
            'status'     => \App\Enums\Content\PublicationStatusEnum::DRAFT,
            'is_visible' => false,
        ]);

        // Ensure the productable is published
        $productable         = $activeProduct->productable;
        $productable->status = \App\Enums\Content\PublicationStatusEnum::PUBLISHED;
        $productable->save();

        // Ensure the product delivery option is published
        $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $activeProduct->id,
            'status'     => \App\Enums\Content\PublicationStatusEnum::PUBLISHED,
        ]);

        $activeProducts = App\Models\Product::activeWithRelations()->get();

        expect($activeProducts)
            ->toHaveCount(1)
            ->and($activeProducts->first()->id)
            ->toEqual($activeProduct->id)
            ->and($activeProducts->first()->relationLoaded('productDeliveryOptions'))
            ->toBeTrue()
            ->and($activeProducts->first()->relationLoaded('productable'))
            ->toBeTrue()
            ->and($activeProducts->first()->relationLoaded('vendor'))
            ->toBeTrue();
    });

    it('active with price and media', function (): void {
        $activeProduct = App\Models\Product::factory()->create([
            'status'     => \App\Enums\Content\PublicationStatusEnum::PUBLISHED,
            'is_visible' => true,
        ]);
        $inactiveProduct = App\Models\Product::factory()->create([
            'status'     => \App\Enums\Content\PublicationStatusEnum::DRAFT,
            'is_visible' => false,
        ]);

        // Ensure the productable is published
        $productable         = $activeProduct->productable;
        $productable->status = \App\Enums\Content\PublicationStatusEnum::PUBLISHED;
        $productable->save();

        // Ensure the product delivery option is published
        $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $activeProduct->id,
            'status'     => \App\Enums\Content\PublicationStatusEnum::PUBLISHED,
        ]);

        $activeProducts = App\Models\Product::activeWithPriceAndMedia()->get();

        expect($activeProducts)
            ->toHaveCount(1)
            ->and($activeProducts->first()->id)
            ->toEqual($activeProduct->id)
            ->and($activeProducts->first()->relationLoaded('productDeliveryOptions'))
            ->toBeTrue()
            ->and($activeProducts->first()->relationLoaded('productable'))
            ->toBeTrue()
            ->and($activeProducts->first()->relationLoaded('vendor'))
            ->toBeTrue();
    });

});

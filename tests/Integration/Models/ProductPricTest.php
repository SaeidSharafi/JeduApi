<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductPrice;

it('to array', function (): void {
    $data = [
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => true,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ];

    $productPrice = ProductPrice::query()->create($data);

    $array = $productPrice->toArray();

    expect($array)
        ->toHaveKeys([
            'id',
            'product_id',
            'min_price',
            'min_original_price',
            'max_price',
            'max_original_price',
            'has_discount',
            'has_featured_price',
            'has_prepayment',
            'discount_percentage',
            'highest_discount_amount',
            'created_at',
            'updated_at',
        ]);
});

it('hasActiveDiscount returns true if has_discount is true', function (): void {
    $productPrice = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => true,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    expect($productPrice->hasActiveDiscount())->toBeTrue();
});

it('hasActiveDiscount returns true if has_featured_price is true', function (): void {
    $productPrice = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => false,
        'has_featured_price'      => true,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    expect($productPrice->hasActiveDiscount())->toBeTrue();
});

it('hasActiveDiscount returns false if both has_discount and has_featured_price are false', function (): void {
    $productPrice = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => false,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    expect($productPrice->hasActiveDiscount())->toBeFalse();
});

it('isSinglePrice returns true if min_price equals max_price', function (): void {
    $productPrice = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 1000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => false,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    expect($productPrice->isSinglePrice())->toBeTrue();
});

it('isSinglePrice returns false if min_price does not equal max_price', function (): void {
    $productPrice = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => false,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    expect($productPrice->isSinglePrice())->toBeFalse();
});

it('getDiscountAmount returns correct discount amount', function (): void {
    $productPrice = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => true,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    expect($productPrice->getDiscountAmount())->toBe(500);
});

it('getPriceRange returns correct min and max prices', function (): void {
    $productPrice = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => true,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    $range = $productPrice->getPriceRange();

    expect($range)
        ->toBeArray()
        ->toHaveKeys(['min', 'max'])
        ->and($range['min'])->toBe(1000)
        ->and($range['max'])->toBe(2000);
});

it('getEffectiveMinPrice returns min_price', function (): void {
    $productPrice = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => true,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    expect($productPrice->getEffectiveMinPrice())->toBe(1000);
});

it('belongs to a product', function (): void {
    $product = Product::factory()->create();

    $productPrice = ProductPrice::query()->create([
        'product_id'              => $product->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => true,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    expect($productPrice->product)->toBeInstanceOf(Product::class)
        ->and($productPrice->product->id)->toBe($product->id);
});

it('scopeWithDiscount returns only products with discounts', function (): void {
    $productWithDiscount = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => true,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    $productWithoutDiscount = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 2000,
        'max_price'               => 3000,
        'min_original_price'      => 2000,
        'max_original_price'      => 3000,
        'has_discount'            => false,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 0,
        'highest_discount_amount' => 0,
    ]);

    $discountedProducts = ProductPrice::withDiscount()->get();

    expect($discountedProducts->count())->toBe(1)
        ->and($discountedProducts->first()->product_id)->toBe($productWithDiscount->product_id);
});

it('scopeWithFeaturedPrice returns only products with featured prices', function (): void {
    $productWithFeaturedPrice = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1000,
        'max_price'               => 2000,
        'min_original_price'      => 1500,
        'max_original_price'      => 2500,
        'has_discount'            => false,
        'has_featured_price'      => true,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 500,
    ]);

    $productWithoutFeaturedPrice = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 2000,
        'max_price'               => 3000,
        'min_original_price'      => 2000,
        'max_original_price'      => 3000,
        'has_discount'            => false,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 0,
        'highest_discount_amount' => 0,
    ]);

    $featuredProducts = ProductPrice::withFeaturedPrice()->get();

    expect($featuredProducts->count())->toBe(1)
        ->and($featuredProducts->first()->product_id)->toBe($productWithFeaturedPrice->product_id);
});

it('scopePriceRange filters products within a price range', function (): void {
    $productInRange = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 1500,
        'max_price'               => 2500,
        'min_original_price'      => 2000,
        'max_original_price'      => 3000,
        'has_discount'            => false,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 0,
        'highest_discount_amount' => 0,
    ]);

    $productOutOfRange = ProductPrice::query()->create([
        'product_id'              => Product::factory()->create()->id,
        'min_price'               => 3000,
        'max_price'               => 4000,
        'min_original_price'      => 3000,
        'max_original_price'      => 4000,
        'has_discount'            => false,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 0,
        'highest_discount_amount' => 0,
    ]);

    $filteredProducts = ProductPrice::priceRange(1000, 2600)->get();

    expect($filteredProducts->count())->toBe(1)
        ->and($filteredProducts->first()->product_id)->toBe($productInRange->product_id);
});

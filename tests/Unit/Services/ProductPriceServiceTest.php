<?php

declare(strict_types=1);

use App\Services\ProductPriceService;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\Course;
use Carbon\Carbon;

beforeEach(function () {
    $this->priceService = app(ProductPriceService::class);
});

describe('ProductPriceService', function () {
    it('returns standard price when no featured or discount price exists', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'featured_price' => null,
            'is_featured' => false,
        ]);

        $currentPrice = $this->priceService->getCurrentPrice($product);

        expect($currentPrice)->toBe(10000);
    });

    it('returns featured price when active and no discount exists', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'featured_price' => 8000,
            'is_featured' => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date' => Carbon::tomorrow(),
        ]);

        $currentPrice = $this->priceService->getCurrentPrice($product);

        expect($currentPrice)->toBe(8000);
    });

    it('returns standard price when featured price is expired', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'featured_price' => 8000,
            'is_featured' => true,
            'featured_price_start_date' => Carbon::parse('-1 week'),
            'featured_price_end_date' => Carbon::yesterday(),
        ]);

        $currentPrice = $this->priceService->getCurrentPrice($product);

        expect($currentPrice)->toBe(10000);
    });

    it('returns standard price when featured price is not yet active', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'featured_price' => 8000,
            'is_featured' => true,
            'featured_price_start_date' => Carbon::tomorrow(),
            'featured_price_end_date' => Carbon::parse('+1 week'),
        ]);

        $currentPrice = $this->priceService->getCurrentPrice($product);

        expect($currentPrice)->toBe(10000);
    });

    it('returns discount price when available (highest priority)', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'featured_price' => 8000,
            'is_featured' => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date' => Carbon::tomorrow(),
        ]);

        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($deliveryOption)
            ->withPrice(6000)
            ->create();

        $currentPrice = $this->priceService->getCurrentPrice($product);

        expect($currentPrice)->toBe(6000);
    });

    it('correctly identifies when product has active discount', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
        ]);

        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($deliveryOption)
            ->withPrice(6000)
            ->create();

        expect($this->priceService->hasActiveDiscount($product))->toBeTrue();
    });

    it('correctly identifies when product has active featured price', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'featured_price' => 8000,
            'is_featured' => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date' => Carbon::tomorrow(),
        ]);

        expect($this->priceService->hasActiveDiscount($product))->toBeTrue();
    });

    it('correctly identifies when product has no active discount', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'featured_price' => null,
            'is_featured' => false,
        ]);

        expect($this->priceService->hasActiveDiscount($product))->toBeFalse();
    });

    it('returns original price correctly', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'featured_price' => 8000,
            'is_featured' => true,
        ]);

        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($deliveryOption)
            ->withPrice(6000)
            ->create();

        $originalPrice = $this->priceService->getOriginalPrice($product);

        expect($originalPrice)->toBe(10000);
    });

    it('handles products without delivery options gracefully', function () {
        $product = Product::factory()->create();

        $currentPrice = $this->priceService->getCurrentPrice($product);
        $originalPrice = $this->priceService->getOriginalPrice($product);
        $hasDiscount = $this->priceService->hasActiveDiscount($product);

        expect($currentPrice)->toBe(0)
            ->and($originalPrice)->toBe(0)
            ->and($hasDiscount)->toBeFalse();
    });

    it('calculates discount percentage correctly', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
        ]);

        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($deliveryOption)
            ->withPrice(7000)
            ->create();

        $discountPercentage = $this->priceService->getDiscountPercentage($product);

        expect($discountPercentage)->toBe(30.0); // 30% off
    });

    it('calculates featured price discount percentage correctly', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
            'featured_price' => 8000,
            'is_featured' => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date' => Carbon::tomorrow(),
        ]);

        $discountPercentage = $this->priceService->getDiscountPercentage($product);

        expect($discountPercentage)->toBe(20.0); // 20% off
    });

    it('returns 0 discount percentage when no discount exists', function () {
        $product = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
        ]);

        $discountPercentage = $this->priceService->getDiscountPercentage($product);

        expect($discountPercentage)->toBe(0.0);
    });
});

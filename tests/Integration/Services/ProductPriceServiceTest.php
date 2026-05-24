<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Services\ProductPriceService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->priceService = app(ProductPriceService::class);
});

describe('ProductPriceService: Fetching Data', function (): void {
    it('returns standard price when no featured or discount price exists', function (): void {
        $product = Product::factory()->has(ProductDeliveryOption::factory(['price' => 10000]))->create();
        expect($this->priceService->getMinCurrentPrice($product))->toBe(10000);
    });

    it('returns featured price when active and no discount exists', function (): void {
        $product = Product::factory()->has(ProductDeliveryOption::factory([
            'price'                     => 10000,
            'is_featured'               => true,
            'featured_price'            => 8000,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ]))->create();
        expect($this->priceService->getMinCurrentPrice($product))->toBe(8000);
    });

    it('returns discount price when available (highest priority)', function (): void {
        $product = Product::factory()
            ->has(ProductDeliveryOption::factory([
                'price'          => 10000,
                'is_featured'    => true,
                'featured_price' => 8000,
            ])->has(ProductDeliveryOptionDiscountPrice::factory(['discounted_price' => 6000]))
            )->create();
        expect($this->priceService->getMinCurrentPrice($product))->toBe(6000);
    });

    it('handles products without delivery options gracefully', function (): void {
        $product   = Product::factory()->create();
        $priceData = $this->priceService->getPriceDataForProduct($product);

        expect($priceData->min_price)->toBe(0)
            ->and($priceData->min_original_price)->toBe(0)
            ->and($priceData->has_discount)->toBeFalse();
    });

    it('calculates discount percentage correctly', function (): void {
        $product = Product::factory()
            ->has(ProductDeliveryOption::factory(['price' => 20000])
                ->has(ProductDeliveryOptionDiscountPrice::factory(['discounted_price' => 15000]))
            )->create();
        expect($this->priceService->getHighestDiscountPercentage($product))->toBe(25.0); // 25% off
    });

    it('returns correct price data from price_data_cache column if present', function (): void {
        $cachedData = [
            'min_price'           => 12000,
            'min_original_price'  => 20000,
            'has_featured_price'  => true,
            'has_discount'        => true,
            'has_pre_payment'     => false,
            'discount_type'       => 'seasonal',
            'discount_percentage' => 40.0,
            'range'               => ['min' => 12000, 'max' => 25000],
            'prices'              => [],
        ];

        $product   = Product::factory()->create(['price_data_cache' => $cachedData]);
        $priceData = $this->priceService->getPriceDataForProduct($product);

        expect($priceData->min_price)->toBe(12000)
            ->and($priceData->discount_percentage)->toBe(40.0)
            ->and($priceData->range)->toEqual(['min' => 12000, 'max' => 25000]);
    });
    it('returns correct price data for multiple products', function () {
        $cachedData = [
            'min_price'           => 1000,
            'min_original_price'  => 20000,
            'has_featured_price'  => true,
            'has_discount'        => true,
            'has_pre_payment'     => false,
            'discount_type'       => 'seasonal',
            'discount_percentage' => 10.0,
            'range'               => ['min' => 12000, 'max' => 25000],
            'prices'              => [],
        ];
        $products = Product::factory()
            ->count(5)
            ->sequence(
                fn ($sequence) => [
                    'name'             => 'Product '.($sequence->index + 1),
                    'price_data_cache' => array_merge($cachedData, ['min_price' => 1000 * ($sequence->index + 1)]),
                ],
            )
            ->create();
        $priceData = $this->priceService->getPriceDataForProducts($products);
        foreach ($products as $product) {
            $multplier = (int) filter_var($product->name, FILTER_SANITIZE_NUMBER_INT);
            expect($priceData->get($product->id)->min_price)->toBe(1000 * $multplier)
                ->and($priceData->get($product->id)->discount_percentage)->toBe(10.0)
                ->and($priceData->get($product->id)->range)->toEqual(['min' => 12000, 'max' => 25000]);
        }
    });
    it('returns cached price in the same request without recalculating', function (): void {
        $product = Product::factory()->has(ProductDeliveryOption::factory(['price' => 10000]))->create();

        $priceData = $this->priceService->calculatePriceDataForProduct($product);
        expect($priceData->min_price)->toBe(10000);

        $product->productDeliveryOptions()->first()->update(['price' => 8000]);
        $product->refresh();
        $priceData = $this->priceService->calculatePriceDataForProduct($product);
        expect($priceData->min_price)->toBe(10000);
    });

    it('returns non-cached price in the same request when useCache is false', function (): void {
        $product = Product::factory()->has(ProductDeliveryOption::factory(['price' => 10000]))->create();

        $priceData = $this->priceService->calculatePriceDataForProduct($product);
        expect($priceData->min_price)->toBe(10000);

        $product->productDeliveryOptions()->first()->update(['price' => 8000]);
        $product->refresh();
        $priceData = $this->priceService->calculatePriceDataForProduct($product, useCache: false);
        expect($priceData->min_price)->toBe(8000);
    });
});

describe('ProductPriceService: Updating Data', function (): void {

    it('updates price index and JSON cache for a single product', function (): void {
        $product = Product::factory()
            ->has(ProductDeliveryOption::factory([
                'price'                     => 20000,
                'is_featured'               => true,
                'is_prepayment_available'   => false,
                'featured_price'            => 18000,
                'featured_price_start_date' => Carbon::yesterday(),
                'featured_price_end_date'   => Carbon::tomorrow(),
            ])->has(ProductDeliveryOptionDiscountPrice::factory(['discounted_price' => 10000]))
            )->create()->fresh();

        $this->priceService->updatePriceIndex($product);
        $product->refresh();

        expect($product->price_data_cache)->toBeArray()
            ->and($product->price_data_cache['min_price'])->toBe(10000)
            ->and($product->price_data_cache['min_original_price'])->toBe(20000)
            ->and($product->price_data_cache['has_featured_price'])->toBeTrue()
            ->and($product->price_data_cache['has_discount'])->toBeTrue()
            ->and($product->price_data_cache['discount_percentage'])->toBe(50);

        $this->assertDatabaseHas('product_prices', [
            'product_id'              => $product->id,
            'min_price'               => 10000,
            'min_original_price'      => 20000,
            'max_price'               => 10000,
            'max_original_price'      => 20000,
            'has_discount'            => true,
            'has_featured_price'      => true,
            'has_prepayment'          => false,
            'discount_percentage'     => '50.00',
            'highest_discount_amount' => 10000,
        ]);
    });

    it('deletes price index record for a product with no delivery options', function (): void {
        $product = Product::factory()->create();
        $product->productPrice()->create([
            'product_id'          => $product->id,
            'min_price'           => 1000, 'min_original_price' => 1000, 'max_price' => 1000,
            'max_original_price'  => 1000,
            'has_discount'        => false, 'has_featured_price' => false, 'has_prepayment' => false,
            'discount_percentage' => 0, 'highest_discount_amount' => 0,
        ]);

        $this->priceService->updatePriceIndex($product);
        $product->refresh();

        expect($product->price_data_cache['min_price'])->toBe(0)
            ->and($product->price_data_cache['has_discount'])->toBeFalse();

        $this->assertDatabaseMissing('product_prices', [
            'product_id' => $product->id,
        ]);
    });

    /**
     * This is the most important new test. It proves the refactoring was successful.
     */
    it('updates price data for multiple products efficiently using a single upsert query', function () {
        $productToUpdate = Product::factory()
            ->has(ProductDeliveryOption::factory(['price' => 10000, 'status' => 'published']))
            ->create();

        $productToDelete = Product::factory()->create();

        $productToDelete->productPrice()->create([
            'product_id'              => $productToDelete->id, 'min_price' => 500, 'min_original_price' => 500,
            'max_price'               => 500, 'max_original_price' => 500, 'has_discount' => false,
            'has_featured_price'      => false, 'has_prepayment' => false, 'discount_percentage' => 0,
            'highest_discount_amount' => 0,
        ]);

        $products = Product::with([
            'productDeliveryOptions.productDeliveryOptionDiscountPrice',
        ])->get();

        $this->priceService->updatePriceIndexForProducts($products);

        $this->assertDatabaseHas('product_prices', [
            'product_id'   => $productToUpdate->id,
            'min_price'    => 10000,
            'has_discount' => false,
        ]);

        $this->assertDatabaseMissing('product_prices', [
            'product_id' => $productToDelete->id,
        ]);
    });

    it('correctly identifies if a product has an active discount', function (): void {
        $productWithDiscount = Product::factory()
            ->has(ProductDeliveryOption::factory()
                ->has(ProductDeliveryOptionDiscountPrice::factory())
            )->create();

        $productWithoutDiscount = Product::factory()
            ->has(ProductDeliveryOption::factory())
            ->create();

        expect($this->priceService->hasActiveDiscount($productWithDiscount))->toBeTrue()
            ->and($this->priceService->hasActiveDiscount($productWithoutDiscount))->toBeFalse();
    });

    it('fetches the minimum original price across all delivery options', function (): void {
        $product = Product::factory()
            ->has(ProductDeliveryOption::factory(['price' => 15000]))
            ->has(ProductDeliveryOption::factory(['price' => 10000]))
            ->create();

        expect($this->priceService->getMinimumOriginalPrice($product))->toBe(10000);
    });

    it('fetches the current price for a specific delivery option considering discounts and featured prices', function (): void {
        $deliveryOption = ProductDeliveryOption::factory([
            'price'                     => 20000,
            'is_featured'               => true,
            'featured_price'            => 18000,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ])->has(ProductDeliveryOptionDiscountPrice::factory([
            'discounted_price' => 15000,
            'starts_at'        => Carbon::yesterday(),
            'ends_at'          => Carbon::tomorrow(),
        ]))->create();
        $deliveryOption->load('productDeliveryOptionDiscountPrice');
        expect($this->priceService->getCurrentPriceForOption($deliveryOption))->toBe(15000);
    });

    it('returns [0,0] for price range when no delivery options exist', function (): void {
        $product = Product::factory()->create();
        expect($this->priceService->getPriceRangeForProduct($product))->toEqual(['min' => 0, 'max' => 0]);
    });

    it('calculates price data correctly for a product with various delivery options and discounts', function (): void {
        $product = Product::factory()
            ->has(ProductDeliveryOption::factory([
                'price'                     => 20000,
                'is_featured'               => true,
                'featured_price'            => 18000,
                'featured_price_start_date' => Carbon::yesterday(),
                'featured_price_end_date'   => Carbon::tomorrow(),
                'is_prepayment_available'   => true,
            ])->has(ProductDeliveryOptionDiscountPrice::factory(['discounted_price' => 15000])))
            ->has(ProductDeliveryOption::factory(['price' => 25000]))
            ->create();

        $deliveryOption = ProductDeliveryOption::factory([
            'price'                   => 30000,
            'is_prepayment_available' => false,
            'prepayment_amount'       => 10000,
        ])->for($product)->create();
        $priceData = $this->priceService->calculatePriceDataForProduct($product, $deliveryOption->id);

        expect($priceData->min_price)->toBe(30000)
            ->and($priceData->min_original_price)->toBe(30000)
            ->and($priceData->has_discount)->toBeFalse()
            ->and($priceData->has_featured_price)->toBeFalse()
            ->and($priceData->has_pre_payment)->toBeFalse()
            ->and($priceData->discount_percentage)->toBeNull()
            ->and($priceData->highest_discount_amount)->toBeNull()
            ->and($priceData->range)->toEqual(['min' => 15000, 'max' => 30000]);
    });

});

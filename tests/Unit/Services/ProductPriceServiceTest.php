<?php

declare(strict_types=1);

use App\Data\Shop\ProductPriceData;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Services\ProductPriceService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->priceService = app(ProductPriceService::class);
});

describe('ProductPriceService', function (): void {
    it('returns standard price when no featured or discount price exists', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'     => $product->id,
            'price'          => 10000,
            'featured_price' => null,
            'is_featured'    => false,
        ]);

        $currentPrice = $this->priceService->getMinCurrentPrice($product);

        expect($currentPrice)->toBe(10000);
    });

    it('returns featured price when active and no discount exists', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'                => $product->id,
            'price'                     => 10000,
            'featured_price'            => 8000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ]);

        $currentPrice = $this->priceService->getMinCurrentPrice($product);

        expect($currentPrice)->toBe(8000);
    });

    it('returns standard price when featured price is expired', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'                => $product->id,
            'price'                     => 10000,
            'featured_price'            => 8000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::parse('-1 week'),
            'featured_price_end_date'   => Carbon::yesterday(),
        ]);

        $currentPrice = $this->priceService->getMinCurrentPrice($product);

        expect($currentPrice)->toBe(10000);
    });

    it('returns standard price when featured price is not yet active', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'                => $product->id,
            'price'                     => 10000,
            'featured_price'            => 8000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::tomorrow(),
            'featured_price_end_date'   => Carbon::parse('+1 week'),
        ]);

        $currentPrice = $this->priceService->getMinCurrentPrice($product);

        expect($currentPrice)->toBe(10000);
    });

    it('returns discount price when available (highest priority)', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'                => $product->id,
            'price'                     => 10000,
            'featured_price'            => 8000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ]);

        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($deliveryOption)
            ->withPrice(6000)
            ->create();

        $currentPrice = $this->priceService->getMinCurrentPrice($product);

        expect($currentPrice)->toBe(6000);
    });

    it('correctly identifies when product has active discount', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 10000,
        ]);

        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($deliveryOption)
            ->withPrice(6000)
            ->create();

        expect($this->priceService->hasActiveDiscount($product))->toBeTrue();
    });

    it('correctly identifies when product has active featured price', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'                => $product->id,
            'price'                     => 10000,
            'featured_price'            => 8000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ]);

        expect($this->priceService->hasActiveDiscount($product))->toBeTrue();
    });

    it('correctly identifies when product has no active discount', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'     => $product->id,
            'price'          => 10000,
            'featured_price' => null,
            'is_featured'    => false,
        ]);

        expect($this->priceService->hasActiveDiscount($product))->toBeFalse();
    });

    it('returns original price correctly', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'     => $product->id,
            'price'          => 10000,
            'featured_price' => 8000,
            'is_featured'    => true,
        ]);

        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($deliveryOption)
            ->withPrice(6000)
            ->create();

        $originalPrice = $this->priceService->getMinimumOriginalPrice($product);

        expect($originalPrice)->toBe(10000);
    });

    it('handles products without delivery options gracefully', function (): void {
        $product = Product::factory()->create();

        $currentPrice  = $this->priceService->getMinCurrentPrice($product);
        $originalPrice = $this->priceService->getMinimumOriginalPrice($product);
        $hasDiscount   = $this->priceService->hasActiveDiscount($product);

        expect($currentPrice)->toBe(0)
            ->and($originalPrice)->toBe(0)
            ->and($hasDiscount)->toBeFalse();
    });

    it('calculates discount percentage correctly', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 10000,
        ]);

        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($deliveryOption)
            ->withPrice(7000)
            ->create();

        $discountPercentage = $this->priceService->getHighestDiscountPercentage($product);

        expect($discountPercentage)->toBe(30.0); // 30% off
    });

    it('calculates featured price discount percentage correctly', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'                => $product->id,
            'price'                     => 10000,
            'featured_price'            => 8000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ]);

        $discountPercentage = $this->priceService->getHighestDiscountPercentage($product);

        expect($discountPercentage)->toBe(20.0); // 20% off
    });

    it('returns 0 discount percentage when no discount exists', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 10000,
        ]);

        $discountPercentage = $this->priceService->getHighestDiscountPercentage($product);

        expect($discountPercentage)->toBe(0.0);
    });

    it('returns correct price data for multiple products', function (): void {
        $product1        = Product::factory()->create();
        $deliveryOption1 = ProductDeliveryOption::factory()->create([
            'product_id' => $product1->id,
            'price'      => 10000,
        ]);

        $product2        = Product::factory()->create();
        $deliveryOption2 = ProductDeliveryOption::factory()->create([
            'product_id'                => $product2->id,
            'price'                     => 20000,
            'featured_price'            => 15000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ]);

        $priceData = $this->priceService->getPriceDataForProducts(Product::all());

        expect($priceData[$product1->id]->min_price)->toBe(10000)
            ->and($priceData[$product1->id]->min_original_price)->toBe(10000)
            ->and($priceData[$product1->id]->discount_percentage)->toBeNull()
            ->and($priceData[$product2->id]->min_price)->toBe(15000)
            ->and($priceData[$product2->id]->min_original_price)->toBe(20000)
            ->and($priceData[$product2->id]->discount_percentage)->toBe(25.0);
    });

    it('returns correct price data for a specific delivery option', function (): void {
        $product         = Product::factory()->create();
        $deliveryOption1 = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 10000,
        ]);
        $deliveryOption2 = ProductDeliveryOption::factory()->create([
            'product_id'                => $product->id,
            'price'                     => 20000,
            'featured_price'            => 15000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ]);

        $priceData1 = $this->priceService->calculatePriceDataForProduct($product, $deliveryOption1->id);
        $priceData2 = $this->priceService->calculatePriceDataForProduct($product, $deliveryOption2->id);

        expect($priceData1->min_price)->toBe(10000)
            ->and($priceData1->min_original_price)->toBe(10000)
            ->and($priceData1->discount_percentage)->toBeNull()
            ->and($priceData2->min_price)->toBe(15000)
            ->and($priceData2->min_original_price)->toBe(20000)
            ->and($priceData2->discount_percentage)->toBe(25.0);
    });
    it('returns correct price range for a product with multiple delivery options', function (): void {
        $product         = Product::factory()->create();
        $deliveryOption1 = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 10000,
        ]);
        $deliveryOption2 = ProductDeliveryOption::factory()->create([
            'product_id'                => $product->id,
            'price'                     => 20000,
            'featured_price'            => 15000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ]);
        $deliveryOption3 = ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 30000,
        ]);

        $priceRange = $this->priceService->getPriceRangeForProduct($product);

        expect($priceRange['min'])->toBe(10000)
            ->and($priceRange['max'])->toBe(30000);

    });
    it('returns [0,0] price range for a product with no delivery options', function (): void {
        $product = Product::factory()->create();

        $priceRange = $this->priceService->getPriceRangeForProduct($product);

        expect($priceRange['min'])->toBe(0)
            ->and($priceRange['max'])->toBe(0);

    });

    it('returns correct current price for a specific delivery option', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'                => $product->id,
            'price'                     => 20000,
            'featured_price'            => 15000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ]);

        $currentPrice = $this->priceService->getCurrentPriceForOption($deliveryOption);

        expect($currentPrice)->toBe(15000);
    });

    it('returns correct prices if the procuts exist in requestCacheService', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'     => $product->id,
            'price'          => 10000,
            'featured_price' => null,
            'is_featured'    => false,
        ]);

        // mock the requestCacheService
        $mockRequestCacheService = Mockery::mock(App\Services\RequestDataCacheService::class);
        $mockRequestCacheService->shouldReceive('hasPriceData')
            ->with($product->id)
            ->andReturn(true);
        $mockRequestCacheService->shouldReceive('getPriceDataForProduct')
            ->with($product->id)
            ->andReturn(new ProductPriceData(
                min_price: 9000,
                min_original_price: 12000,
                has_featured_price: false,
                has_discount: true,
                has_pre_payment: false,
                discount_type: 'promotional',
                discount_percentage: 25.0,
                range: ['min' => 9000, 'max' => 15000],
                prices: collect([]),
            ));

        $this->priceService = new ProductPriceService($mockRequestCacheService);
        $pirceData          = $this->priceService->calculatePriceDataForProduct($product);

        expect($pirceData->min_price)->toBe(9000)
            ->and($pirceData->min_original_price)->toBe(12000)
            ->and($pirceData->has_discount)->toBeTrue()
            ->and($pirceData->discount_percentage)->toBe(25.0);

    });

    it('returns correct price data for a product from price_data_cache column', function (): void {
        $product        = Product::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'                => $product->id,
            'price'                     => 15000,
            'featured_price'            => 15000,
            'is_featured'               => true,
            'featured_price_start_date' => Carbon::yesterday(),
            'featured_price_end_date'   => Carbon::tomorrow(),
        ]);

        ProductDeliveryOptionDiscountPrice::factory()
            ->forProductDeliveryOption($deliveryOption)
            ->withPrice(6000)
            ->create();
        // Simulate price_data_cache being set
        $product->price_data_cache = [
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
        $product->save();
        $product->refresh();

        $priceData = $this->priceService->getPriceDataForProduct($product);

        expect($priceData->min_price)->toBe(12000)
            ->and($priceData->min_original_price)->toBe(20000)
            ->and($priceData->has_featured_price)->toBeTrue()
            ->and($priceData->has_discount)->toBeTrue()
            ->and($priceData->discount_type)->toBe('seasonal')
            ->and($priceData->discount_percentage)->toBe(40.0)
            ->and($priceData->range)->toEqual(['min' => 12000, 'max' => 25000]);

    });

});

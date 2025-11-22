<?php

declare(strict_types=1);

uses(Tests\Support\Traits\AuthTestTrait::class);
describe('RequestDataCacheService', function (): void {
    beforeEach(function (): void {
        $this->service = new App\Services\RequestDataCacheService();
    });

    it('can store and retrieve products', function (): void {
        $product1 = App\Models\Product::factory()->create();
        $product2 = App\Models\Product::factory()->create();

        expect($this->service->hasProduct($product1->id))->toBeFalse();
        expect($this->service->getProduct($product1->id))->toBeNull();

        $this->service->storeProducts(collect([$product1, $product2]));

        expect($this->service->hasProduct($product1->id))->toBeTrue()
            ->and($this->service->getProduct($product1->id)->id)->toEqual($product1->id)
            ->and($this->service->hasProduct($product2->id))->toBeTrue()
            ->and($this->service->getProduct($product2->id)->id)->toEqual($product2->id);
    });

    it('can store and retrieve product price data', function (): void {
        $product   = App\Models\Product::factory()->create();
        $priceData = new App\Data\Shop\ProductPriceData(
            min_price: 1000,
            min_original_price: 1200,
            has_featured_price: true,
            has_discount: true,
            has_pre_payment: false,
            discount_type: 'percentage',
            discount_percentage: 16.67,
            highest_discount_amount: 200,
            range: [1000, 1500],
            prices: collect(),
        );

        expect($this->service->hasPriceData($product->id))->toBeFalse()
            ->and($this->service->getPriceDataForProduct($product->id))->toBeNull();

        $this->service->storeProductPriceData($product->id, $priceData);

        expect($this->service->hasPriceData($product->id))->toBeTrue()
            ->and($this->service->getPriceDataForProduct($product->id))->toEqual($priceData);
    });

});

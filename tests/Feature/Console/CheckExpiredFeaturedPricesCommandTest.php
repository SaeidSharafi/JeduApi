<?php

describe('CheckExpiredFeaturedPricesCommand', function (){
    it('handles no expired featured prices', function (): void {
        $this->artisan('prices:check-expired-featured')
            ->expectsOutput('Checking for expired featured prices...')
            ->expectsOutput('No expired featured prices found.')
            ->assertExitCode(0);
    });

    it('handles expired featured prices without dry-run', function (): void {
        // Arrange
        $mockedRequestService = mock(\App\Services\RequestDataCacheService::class);
        $mockedRequestService->shouldReceive('hasPriceData')->andReturnFalse();
        $product = App\Models\Product::factory()->create();

        $option = App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'is_featured' => true,
            'featured_price_start_date' => now()->subDays(2),
            'featured_price_end_date' => now()->addDay(),
        ]);
        $priceService = app(App\Services\ProductPriceService::class);
        $priceService->updatePriceIndexForProducts(collect([$product]));
        $product->refresh();
        $priceIndex = $product->productPrice;
        $priceCacheCol = $product->price_data_cache;
        expect($priceIndex)->not->toBeNull()
            ->and($priceCacheCol)->not->toBeNull()
            ->and($priceIndex->has_featured_price)->toBeTrue()
            ->and($priceCacheCol['has_featured_price'])->toBeTrue();

        $this->travel(2)->days();

        $this->artisan('prices:check-expired-featured')
            ->expectsOutput('Checking for expired featured prices...')
            ->expectsOutput("Found 1 expired featured prices.")
            ->assertExitCode(0);

        $product->refresh();
        $priceCacheCol = $product->price_data_cache;
        expect($priceCacheCol)->not->toBeNull()
            ->and($priceCacheCol['has_featured_price'])->toBeFalse();

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'has_featured_price' => false,
        ]);

    });

    it('handles expired featured prices with dry-run', function (): void {
        // Arrange
        $product = App\Models\Product::factory()->create();
        $option = App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'is_featured' => true,
            'featured_price_end_date' => now()->subDay(),
        ]);

        // Act & Assert
        $this->artisan('prices:check-expired-featured --dry-run')
            ->expectsOutput('Checking for expired featured prices...')
            ->expectsOutput("Found 1 expired featured prices.")
            ->expectsOutput('DRY RUN MODE - No changes will be made')
            ->assertExitCode(0);

        $option->refresh();
        expect($option->is_featured)->toBeTrue();
        expect($option->featured_price_end_date)->not->toBeNull();
    });

    it('handles lock already acquired', function (): void {
        // Arrange
        $lock = \Illuminate\Support\Facades\Cache::lock('price-indexing', 60);
        $lock->get(); // Acquire the lock

        // Act & Assert
        $this->artisan('prices:check-expired-featured')
            ->expectsOutput('Another price indexing operation is already running. Skipping expired price check.')
            ->assertExitCode(0);

        // Cleanup
        $lock->release();
    });

    it('handles dry-run with no expired prices', function (): void {
        $this->artisan('prices:check-expired-featured --dry-run')
            ->expectsOutput('Checking for expired featured prices...')
            ->expectsOutput('No expired featured prices found.')
            ->assertExitCode(0);
    });
    it('handles dry-run with expired featured prices', function (): void {
        // Arrange
        $product = App\Models\Product::factory()->create();
        App\Models\ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'is_featured' => true,
            'featured_price_end_date' => now()->addDay(),
        ]);
        $priceService = app(App\Services\ProductPriceService::class);
        $priceService->updatePriceIndexForProducts(collect([$product]));

        $this->travel(3)->days();
        // Act & Assert
        $this->artisan('prices:check-expired-featured --dry-run')
            ->expectsOutput('Checking for expired featured prices...')
            ->expectsOutput("Found 1 expired featured prices.")
            ->expectsOutput('DRY RUN MODE - No changes will be made')
            ->assertExitCode(0);
        $product->refresh();
        expect($product->productPrice)->not->toBeNull()
            ->and($product->price_data_cache)->not->toBeNull()
            ->and($product->productPrice->has_featured_price)->toBeTrue()
            ->and($product->price_data_cache['has_featured_price'])->toBeTrue();
    });
});

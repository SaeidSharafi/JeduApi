<?php

declare(strict_types=1);

// 1. Use statements for all necessary classes
use App\Console\Commands\IndexAllProductPricesCommand;
use App\Jobs\UpdateProductPricingJob;
use App\Models\Product;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

it('processes all published products into a single chunk without --missing-only option', function (): void {
    Queue::fake();
    $products = Product::factory()->count(5)->create(['status' => 'published']);

    artisan(IndexAllProductPricesCommand::class)
        ->expectsOutputToContain('Found 5 products to process.')
        ->assertExitCode(0);

    // Assert: Check that exactly ONE job was pushed due to chunking
    Queue::assertPushed(UpdateProductPricingJob::class, 1);

    // Assert (more specifically): Check that the single job contains all 5 product IDs
    Queue::assertPushed(UpdateProductPricingJob::class, function ($job) use ($products) {
        // The job's payload should be an array of IDs.
        // We sort both arrays to ensure the comparison is order-independent and accurate.
        return count($job->productIds) === 5
            && empty(array_diff($job->productIds, $products->pluck('id')->all()));
    });
});

it('shows appropriate message when no products need processing', function (): void {
    Queue::fake();
    $product = Product::factory()->create(['status' => 'published']);
    $product->productPrice()->create([
        'min_price'          => 10000,
        'min_original_price' => 10000,
        'max_price'          => 10000,
        'max_original_price' => 10000,
        'has_discount'       => false,
        'has_featured_price' => false,
        'has_prepayment'     => false,
    ]);

    // Act: Run the command with --missing-only
    artisan(IndexAllProductPricesCommand::class, ['--missing-only' => true])
        ->expectsOutput('No products found missing price index entries.')
        ->assertExitCode(0);

    // Assert: Verify that no jobs were dispatched to the queue
    Queue::assertNotPushed(UpdateProductPricingJob::class);
});

it('handles synchronous processing by updating the database directly', function (): void {
    // Arrange: Create one product that needs its price index generated
    $product = Product::factory()
        ->withDeliveryOptions(realData: [
            ['price' => 15000],
        ])
        ->create(['status' => 'published']);

    artisan(IndexAllProductPricesCommand::class, [
        '--missing-only' => true,
        '--sync'         => true,
    ])->expectsOutputToContain('Found 1 products to process.')
        ->assertExitCode(0);

    $this->assertDatabaseHas('product_prices', [
        'product_id' => $product->id,
    ]);
});

it('handles lock already acquired', function (): void {
    $lock = Illuminate\Support\Facades\Cache::lock('price-indexing', 60);
    $lock->get(); // Acquire the lock

    artisan(IndexAllProductPricesCommand::class)
        ->expectsOutput('Another price indexing operation is already running. Skipping.')
        ->assertExitCode(0);

    $lock->release();
});

it('show appropriate message when no published products exist', function (): void {
    Product::factory()->count(3)->create(['status' => 'draft']);

    artisan(IndexAllProductPricesCommand::class)
        ->expectsOutput('No published products found to index.')
        ->assertExitCode(0);

    artisan(IndexAllProductPricesCommand::class, ['--missing-only' => true])
        ->expectsOutput('No products found missing price index entries.')
        ->assertExitCode(0);

});

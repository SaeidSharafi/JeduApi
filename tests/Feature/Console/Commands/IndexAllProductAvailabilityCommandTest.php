<?php

declare(strict_types=1);

use App\Console\Commands\IndexAllProductAvailabilityCommand;
use App\Jobs\UpdateProductAvailabilityJob;
use App\Models\Product;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

it('dispatches availability indexing jobs for every product', function (): void {
    Queue::fake();
    $products = Product::factory()->count(3)->create();

    artisan(IndexAllProductAvailabilityCommand::class)
        ->expectsOutputToContain('Found 3 products to process.')
        ->assertSuccessful();

    Queue::assertPushed(UpdateProductAvailabilityJob::class, function (UpdateProductAvailabilityJob $job) use ($products): bool {
        return $job->productIds === $products->pluck('id')->all();
    });
});

it('can synchronously backfill product availability', function (): void {
    $product = Product::factory()->withDeliveryOptions(1)->create();

    artisan(IndexAllProductAvailabilityCommand::class, ['--sync' => true])
        ->assertSuccessful();

    expect($product->fresh()->has_published_delivery_option)->toBeTrue();
});

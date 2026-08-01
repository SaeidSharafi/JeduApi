<?php

declare(strict_types=1);

use App\Events\ProductSearchIndexInvalidated;
use App\Jobs\UpdateProductPricingJob;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\ProductPriceService;
use Illuminate\Support\Facades\Event;

it('handle empty product ids', function (): void {
    $job = new UpdateProductPricingJob([]);
    $job->handle(app(ProductPriceService::class));
    $this->assertTrue(true);
});

it('handle non existing product ids', function (): void {
    $job = new UpdateProductPricingJob([9999, 10000]);
    $job->handle(app(ProductPriceService::class));
    $this->assertTrue(true);
});

it('synchronizes search only when indexed pricing values change', function (): void {
    $product = Product::withoutSyncingToSearch(fn (): Product => Product::factory()->create());
    ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 250_000,
    ]);

    $transactionManager = fakeAfterCommitEventsImmediately(ProductSearchIndexInvalidated::class);
    (new UpdateProductPricingJob([$product->id]))->handle(app(ProductPriceService::class));
    restoreAfterCommitEventManager($transactionManager);

    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);

    $transactionManager = fakeAfterCommitEventsImmediately(ProductSearchIndexInvalidated::class);
    (new UpdateProductPricingJob([$product->id]))->handle(app(ProductPriceService::class));
    restoreAfterCommitEventManager($transactionManager);

    Event::assertNotDispatched(ProductSearchIndexInvalidated::class);
});

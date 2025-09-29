<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProductCacheInvalidated;
use App\Jobs\UpdateProductPricingJob;

final class QueueProductPriceCacheUpdate
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(ProductCacheInvalidated $event): void
    {
        UpdateProductPricingJob::dispatch([$event->productId]);
    }
}

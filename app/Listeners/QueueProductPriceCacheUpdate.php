<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProductCacheInvalidated;
use App\Jobs\UpdateProductPriceCacheJob;

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
        UpdateProductPriceCacheJob::dispatch($event->productId);
    }
}

<?php

namespace App\Listeners;

use App\Events\ProductCacheInvalidated;
use App\Jobs\UpdateProductPriceCacheJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class QueueProductPriceCacheUpdate
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     */
    public function handle(ProductCacheInvalidated $event): void
    {
        UpdateProductPriceCacheJob::dispatch($event->productId);
    }
}

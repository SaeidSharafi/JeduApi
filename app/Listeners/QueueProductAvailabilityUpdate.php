<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProductAvailabilityCacheInvalidated;
use App\Jobs\UpdateProductAvailabilityJob;

final class QueueProductAvailabilityUpdate
{
    public function handle(ProductAvailabilityCacheInvalidated $event): void
    {
        UpdateProductAvailabilityJob::dispatch($event->productIds);
    }
}

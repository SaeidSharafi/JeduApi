<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProductSearchIndexInvalidated;
use App\Jobs\SynchronizeProductSearchIndexJob;

final class QueueProductSearchIndexSynchronization
{
    public function handle(ProductSearchIndexInvalidated $event): void
    {
        SynchronizeProductSearchIndexJob::dispatch($event->productIds);
    }
}

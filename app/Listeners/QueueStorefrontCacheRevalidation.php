<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\System\StorefrontTag;
use App\Events\StorefrontCacheInvalidated;
use App\Jobs\RevalidateStorefrontCacheJob;

final class QueueStorefrontCacheRevalidation
{
    public function handle(StorefrontCacheInvalidated $event): void
    {
        if ($event->tags === []) {
            return;
        }

        RevalidateStorefrontCacheJob::dispatch(array_values(array_unique(array_map(
            static fn (StorefrontTag $tag): string => $tag->value,
            $event->tags,
        ))));
    }
}

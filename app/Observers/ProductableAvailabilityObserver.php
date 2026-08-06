<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use Illuminate\Database\Eloquent\Model;

final class ProductableAvailabilityObserver
{
    private const array SEARCHABLE_FIELDS = [
        'full_name',
        'short_name',
        'description',
        'difficulty_level',
        'slug',
    ];

    public function updated(Model $productable): void
    {
        $statusChanged = $productable->wasChanged('status');

        if ($statusChanged) {
            // Symmetric invalidation: both PUBLISHED → non-published (product becomes
            // unavailable) AND non-published → PUBLISHED (product becomes available)
            // flip availability. One-way handling misses the publish transition.
            $productIds = $productable->products()->pluck('products.id')->all();

            if ($productIds !== []) {
                ProductAvailabilityCacheInvalidated::dispatch($productIds);
            }
        }

        if ($productable->wasChanged(self::SEARCHABLE_FIELDS)) {
            $this->dispatchSearchInvalidation($productable);
        }
    }

    private function dispatchSearchInvalidation(Model $productable): void
    {
        $productIds = $productable->products()->pluck('products.id')->all();

        if ($productIds !== []) {
            ProductSearchIndexInvalidated::dispatch($productIds);
        }
    }
}

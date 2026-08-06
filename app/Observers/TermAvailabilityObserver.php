<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ProductAvailabilityCacheInvalidated;
use App\Models\Term;
use Illuminate\Database\Eloquent\Collection;

final class TermAvailabilityObserver
{
    public function updated(Term $term): void
    {
        if (! $term->wasChanged('status')) {
            return;
        }

        // Symmetric invalidation: both ACTIVE → non-active (products become unavailable)
        // AND non-active → ACTIVE (products become available) flip availability.
        $term->products()
            ->select('products.id')
            ->chunkById(200, function (Collection $products): void {
                ProductAvailabilityCacheInvalidated::dispatch($products->pluck('id')->all());
            });
    }
}

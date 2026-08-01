<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\TermStatusEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Models\Term;
use Illuminate\Database\Eloquent\Collection;

final class TermAvailabilityObserver
{
    public function updated(Term $term): void
    {
        $currentStatus = $term->status instanceof TermStatusEnum
            ? $term->status->value
            : $term->status;

        if (! $term->wasChanged('status')
            || $term->getRawOriginal('status') !== TermStatusEnum::ACTIVE->value
            || $currentStatus === TermStatusEnum::ACTIVE->value) {
            return;
        }

        $term->products()
            ->select('products.id')
            ->chunkById(200, function (Collection $products): void {
                ProductAvailabilityCacheInvalidated::dispatch($products->pluck('id')->all());
            });
    }
}

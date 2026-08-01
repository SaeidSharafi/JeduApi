<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Content\PublicationStatusEnum;
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
        if (! $productable->wasChanged('status')) {
            if ($productable->wasChanged(self::SEARCHABLE_FIELDS)) {
                $this->dispatchSearchInvalidation($productable);
            }

            return;
        }

        $currentStatus = $productable->status instanceof PublicationStatusEnum
            ? $productable->status->value
            : $productable->status;

        if ($productable->getRawOriginal('status') !== PublicationStatusEnum::PUBLISHED->value
            || $currentStatus === PublicationStatusEnum::PUBLISHED->value) {
            return;
        }

        $productIds = $productable->products()->pluck('products.id')->all();

        if ($productIds !== []) {
            ProductAvailabilityCacheInvalidated::dispatch($productIds);
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

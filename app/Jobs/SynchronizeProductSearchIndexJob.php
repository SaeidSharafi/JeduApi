<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

final class SynchronizeProductSearchIndexJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int[]  $productIds
     */
    public function __construct(public array $productIds) {}

    public function handle(CacheStore $cache): void
    {
        if ($this->productIds === []) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', array_values(array_unique($this->productIds)))
            ->with([
                'productable',
                'categories:id,slug',
                'productPrice',
                'productDeliveryOptions',
                'term:id,status',
            ])
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        /** @var Collection<int, Product> $searchableProducts */
        $searchableProducts = $products
            ->filter(static fn (Product $product): bool => $product->shouldBeSearchable())
            ->values();

        /** @var Collection<int, Product> $unsearchableProducts */
        $unsearchableProducts = $products
            ->reject(static fn (Product $product): bool => $product->shouldBeSearchable())
            ->values();

        $engine = $products->first()->searchableUsing();

        if ($searchableProducts->isNotEmpty()) {
            $engine->update($searchableProducts);
        }

        if ($unsearchableProducts->isNotEmpty()) {
            $engine->delete($unsearchableProducts);
        }

        // Cached result pages were built from the previous index generation, so
        // they are cleared once the index reflects the change.
        $cache->invalidate(CacheTag::Search);
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs\Discounts;

use App\Models\Product;
use App\Services\Discounts\ProductDiscountIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SmartCache\Facades\SmartCache;

/**
 * Job to fully regenerate all product discount prices.
 * This should be run during maintenance or when major changes are made to the discount system.
 */
final class RegenerateAllDiscountPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $indexer = app(ProductDiscountIndexer::class);
        $indexer->reIndexComplete();
        $keysToClear = config('cache_invalidation.map.'.Product::class, []);
        foreach ($keysToClear as $key) {
            SmartCache::forget($key->key());
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'discount-promotion',
            'full-reindex',
        ];
    }
}

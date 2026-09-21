<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteProductAction
{
    public function __construct(private CacheStore $cache) {}

    public function handle(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            if ($product->orderItems()->exists()) {
                throw ValidationException::withMessages([
                    'product' => __('validation.custom.product.cannot_delete_product_with_orders'),
                ]);
            }
            $product->productDeliveryOptions()->delete();
            $product->delete();
        });

        $this->cache->invalidate(CacheTag::Catalog, CacheTag::Search);
    }
}

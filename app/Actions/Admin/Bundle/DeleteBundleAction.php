<?php

declare(strict_types=1);

namespace App\Actions\Admin\Bundle;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;
use App\Models\Bundle;
use Illuminate\Validation\ValidationException;

final readonly class DeleteBundleAction
{
    public function __construct(private CacheStore $cache) {}

    public function handle(Bundle $bundle): void
    {
        if ($bundle->products()->whereHas('productDeliveryOptions.orderItems')->exists()) {
            $bundle->update(['status' => 'archived']);

            $this->cache->invalidate(CacheTag::Search);

            return;
        }

        if ($bundle->products()->whereHas('productDeliveryOptions.enrollments')->exists()) {
            throw ValidationException::withMessages(['bundle' => 'A used Bundle can only be archived.']);
        }

        $bundle->products()->each(function ($product): void {
            $product->productDeliveryOptions()->delete();
            $product->delete();
        });
        $bundle->delete();

        $this->cache->invalidate(CacheTag::Search);
    }
}

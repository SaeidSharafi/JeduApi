<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Events\ProductCacheInvalidated;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\DB;

final readonly class DeleteProductDeliveryOptionAction
{
    /**
     * Execute the action.
     */
    public function handle(ProductDeliveryOption $deliveryOption): void
    {
        DB::transaction(function () use ($deliveryOption): void {
            $deliveryOption->delete();
        });
        ProductCacheInvalidated::dispatch($deliveryOption->product_id);
    }
}

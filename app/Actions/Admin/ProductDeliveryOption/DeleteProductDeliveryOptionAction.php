<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteProductDeliveryOptionAction
{
    /**
     * Execute the action.
     */
    public function handle(ProductDeliveryOption $deliveryOption): void
    {
        DB::transaction(function () use ($deliveryOption): void {
            // Destructive guard: never delete a delivery option that has
            // enrollments (cascade deletes access records) or order items
            // (nulls the FK on purchased line items, breaking audit history).
            if ($deliveryOption->enrollments()->exists() || $deliveryOption->orderItems()->exists()) {
                throw ValidationException::withMessages([
                    'product_delivery_option' => __('validation.custom.product_delivery_option.cannot_delete_delivery_option_with_orders'),
                ]);
            }

            $deliveryOption->delete();
        });
        ProductCacheInvalidated::dispatch($deliveryOption->product_id);
        ProductAvailabilityCacheInvalidated::dispatch([$deliveryOption->product_id]);
        ProductSearchIndexInvalidated::dispatch([$deliveryOption->product_id]);
    }
}

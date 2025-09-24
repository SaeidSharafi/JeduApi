<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionUpdateData;
use App\Events\ProductCacheInvalidated;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProductDeliveryOptionAction
{
    /**
     * Execute the action.
     */
    public function handle(ProductDeliveryOptionUpdateData $data, ProductDeliveryOption $deliveryOption): ProductDeliveryOption
    {
        $pdo = DB::transaction(function () use ($data, $deliveryOption): ProductDeliveryOption {
            $pdoData = $data->except('teachers')->toArray();
            $deliveryOption->update($pdoData);
            $deliveryOption->teachers()->sync($data->teachers);

            return $deliveryOption->fresh();
        });
        ProductCacheInvalidated::dispatch($pdo->product_id);

        return $pdo;
    }
}

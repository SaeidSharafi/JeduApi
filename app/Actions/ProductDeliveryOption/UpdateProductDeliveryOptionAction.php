<?php

declare(strict_types=1);

namespace App\Actions\ProductDeliveryOption;

use App\Data\ProductDeliveryOption\ProductDeliveryOptionCreateData;
use App\Data\ProductDeliveryOption\ProductDeliveryOptionUpdateData;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProductDeliveryOptionAction
{
    /**
     * Execute the action.
     */
    public function handle(ProductDeliveryOptionUpdateData $data, ProductDeliveryOption $deliveryOption): ProductDeliveryOption
    {
        return DB::transaction(function () use($data,$deliveryOption): ProductDeliveryOption {
            $pdoData = $data->except('teachers')->toArray();
            $deliveryOption->update($pdoData);
            $deliveryOption->teachers()->sync($data->teachers);
            return $deliveryOption->fresh();
        });
    }
}

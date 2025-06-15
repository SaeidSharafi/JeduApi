<?php

declare(strict_types=1);

namespace App\Actions\ProductDeliveryOption;

use App\Data\ProductDeliveryOption\ProductDeliveryOptionCreateData;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\DB;

final readonly class CreateProductDeliveryOptionAction
{
    /**
     * Execute the action.
     */
    public function handle(ProductDeliveryOptionCreateData $data, Product $product): ProductDeliveryOption
    {
        return DB::transaction(function () use($data,$product): ProductDeliveryOption {
            return $product->productDeliveryOptions()->create($data->toArray())->fresh();
        });
    }
}

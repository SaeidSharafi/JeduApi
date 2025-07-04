<?php

namespace App\Data\Order;

use App\Data\Product\ProductData;
use App\Data\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Data\Transformer\TranslatableEnumData;
use App\Data\Vendor\ShowVendorData;
use App\Enums\OrderItemStatusEnum;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class OrderItemListItemData extends Data
{
    public function __construct(
        public int $id,
        public int $product_delivery_option_id,
        public int $discount_amount,
        public int $quantity = 1,
        public int $tax_amount = 0,
        public string $name,
        public string $sku,
    )
    {
    }
}

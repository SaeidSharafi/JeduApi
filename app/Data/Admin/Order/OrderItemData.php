<?php

namespace App\Data\Admin\Order;

use App\Data\Transformer\TranslatableEnumData;
use App\Data\Admin\Vendor\ShowVendorData;
use App\Enums\OrderItemPaymentTypeEnum;
use App\Enums\OrderItemStatusEnum;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class OrderItemData extends Data
{
    public function __construct(
        public int $id,
        public int $Order_id,
        public int $product_delivery_option_id,
        public int $discount_amount,
        public int $qty_ordered = 1,
        public int $tax_amount = 0,
        public string $name,
        public string $sku,
        public int $price,
        public int $total,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderItemPaymentTypeEnum $payment_type,
        public ?int $prepayment_amount = null,
        public ?int $qty_refunded = null,
        public ?int $total_refunded = null,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderItemStatusEnum $status,
        public ShowVendorData $vendor,
        #[MapOutputName('product_snapshot')]
        public array $product_data_snapshot_json,
    )
    {
    }
}

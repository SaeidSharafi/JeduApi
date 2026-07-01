<?php

declare(strict_types=1);

namespace App\Data\Admin\Order;

use App\Data\Admin\Vendor\ShowVendorData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class OrderItemData extends Data
{
    public function __construct(
        public int $id,
        public int $Order_id,
        public int $product_delivery_option_id,
        public int $discount_amount,
        public int $qty_ordered,
        public int $tax_amount,
        public string $name,
        public string $sku,
        public int $price,
        public int $total,
        public int $original_price,
        public int $product_discount_amount,
        public int $total_discount_amount,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderItemPaymentTypeEnum $payment_type,
        public ?int $prepayment_amount,
        public ?int $qty_refunded,
        public ?int $total_refunded,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderItemStatusEnum $status,
        public ShowVendorData $vendor,
        #[MapOutputName('product_snapshot')]
        public array $product_data_snapshot_json,
    ) {}
}

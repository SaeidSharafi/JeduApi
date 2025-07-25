<?php

declare(strict_types=1);

namespace App\Data\Admin\Order;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class OrderItemListItemData extends Data
{
    public function __construct(
        public int $id,
        public int $product_delivery_option_id,
        public int $discount_amount,
        public int $qty_ordered,
        public int $tax_amount,
        public string $name,
        public string $sku,
        public int $price,
        public int $total,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderItemPaymentTypeEnum $payment_type,
        public ?int $prepayment_amount = null,
        public ?int $qty_refunded = null,
        public ?int $total_refunded = null,
    ) {}
}

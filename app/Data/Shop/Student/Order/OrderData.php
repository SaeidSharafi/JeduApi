<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Order;

use App\Contracts\WalletTransactionSourceableDataContract;
use App\Data\Shop\Payment\PaymentData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Order\OrderPaymentStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class OrderData extends Data implements WalletTransactionSourceableDataContract
{
    public function __construct(
        public int $id,
        public string $increment_id,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderStatusEnum $status,
        public string $customer_email,
        public string $customer_phone,
        public string $customer_first_name,
        public string $customer_last_name,
        public int $total_qty_ordered,
        public int $total_item_count,
        public int $subtotal,
        public int $discount_amount,
        public int $tax_amount,
        public int $grand_total,
        public int $full_value_grand_total,
        public int $total_product_discount,
        public int $total_cart_discount,
        public int $total_discount,
        public int $total_paid,
        public int $balance_due,
        public ?string $currency_code,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ?OrderPaymentStatusEnum $payment_status,
        public ?string $applied_coupon_code,
        public ?Verta $created_at,
        public ?Verta $updated_at,
        #[DataCollectionOf(OrderItemData::class)]
        public Collection $items,
        #[DataCollectionOf(PaymentData::class)]
        public ?Collection $payments = null,
    ) {}
}

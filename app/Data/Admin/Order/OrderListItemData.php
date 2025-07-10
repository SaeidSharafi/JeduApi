<?php

namespace App\Data\Admin\Order;

use App\Data\Admin\Payment\PaymentData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class OrderListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $increment_id,
        public string $customer_first_name,
        public string $customer_last_name,
        public string $customer_email,
        public string $customer_phone,
        public int $subtotal,
        public int $discount_amount,
        public int $tax_amount,
        public int $grand_total,
        public int $total_paid,
        public int $balance_due,
        public ?string $admin_notes = null,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderStatusEnum $status,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderPaymentStatusEnum $payment_status,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $updated_at,
        #[DataCollectionOf(PaymentData::class)]
        public Collection $payments,
        #[DataCollectionOf(OrderItemListItemData::class)]
        public Collection $items,

    ) {
    }
}

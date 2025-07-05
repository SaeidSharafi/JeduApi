<?php

namespace App\Data\Order;

use App\Data\Transformer\TranslatableEnumData;
use App\Data\User\ShowUserData;
use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class OrderData extends Data
{
    public function __construct(
        public int $id,
        public string $increment_id,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderStatusEnum $status,
        public int $customer_id,
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
        public int $amount_paid,
        public int $amount_refunded,
        public int $balance_due,
        public ?string $currency_code = null,
        public ?ShowUserData $customer = null,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ?OrderPaymentStatusEnum $payment_status = null,
        public ?string $applied_coupon_code = null,
        public ?string $admin_notes = null,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $updated_at,
        #[MapInputName('customer_snapshot_json')]
        public array $customer_snapshot,
        #[DataCollectionOf(OrderItemData::class)]
        public Collection $items,
    )
    {
    }
}

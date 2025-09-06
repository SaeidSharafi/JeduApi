<?php

declare(strict_types=1);

namespace App\Data\Admin\Order;

use App\Contracts\WalletTransactionSourceableDataContract;
use App\Data\Admin\Payment\PaymentData;
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

final class OrderListItemData extends Data implements WalletTransactionSourceableDataContract
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
        public ?string $admin_notes,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderStatusEnum $status,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderPaymentStatusEnum $payment_status,
        public Verta $created_at,
        public Verta $updated_at,
        #[DataCollectionOf(PaymentData::class)]
        public Collection $payments,
        #[DataCollectionOf(OrderItemListItemData::class)]
        public Collection $items,

    ) {}
}

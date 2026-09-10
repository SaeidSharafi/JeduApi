<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Order;

use App\Contracts\WalletTransactionSourceableDataContract;
use App\Data\Shop\Payment\PaymentData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Order\OrderPaymentStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\BundlePurchase;
use App\Models\Order;
use App\Models\OrderItem;
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
        /**
         * Unified, ordered list of purchased lines: one entry per standalone
         * Order Item and one entry per Bundle Purchase. `type` discriminates the
         * two, and Bundle component lines are nested inside their Bundle entry
         * only — the customer never sees internal component lines as lines.
         *
         * @var list<array<string, mixed>>
         */
        public array $items,
        #[DataCollectionOf(PaymentData::class)]
        public ?Collection $payments = null,
    ) {}

    public static function fromModel(Order $order): self
    {
        $order->loadMissing([
            'standaloneItems.productDeliveryOption',
            'bundlePurchases.components.enrollment',
            'payments',
        ]);

        return new self(
            id: (int) $order->id,
            increment_id: $order->increment_id,
            status: $order->status,
            customer_email: $order->customer_email,
            customer_phone: $order->customer_phone,
            customer_first_name: $order->customer_first_name,
            customer_last_name: $order->customer_last_name,
            total_qty_ordered: (int) $order->total_qty_ordered,
            total_item_count: (int) $order->total_item_count,
            subtotal: $order->subtotal,
            discount_amount: $order->discount_amount,
            tax_amount: $order->tax_amount,
            grand_total: $order->grand_total,
            full_value_grand_total: $order->full_value_grand_total,
            total_product_discount: $order->total_product_discount,
            total_cart_discount: $order->total_cart_discount,
            total_discount: $order->total_discount,
            total_paid: $order->total_paid,
            balance_due: $order->balance_due,
            currency_code: $order->currency_code,
            payment_status: OrderPaymentStatusEnum::from($order->payment_status),
            applied_coupon_code: $order->applied_coupon_code,
            created_at: $order->created_at ? Verta::instance($order->created_at) : null,
            updated_at: $order->updated_at ? Verta::instance($order->updated_at) : null,
            items: self::lineItems($order),
            payments: PaymentData::collect($order->payments),
        );
    }

    /** @return list<array<string, mixed>> */
    private static function lineItems(Order $order): array
    {
        $lines = [];

        foreach ($order->standaloneItems as $item) {
            /** @var OrderItem $item */
            $lines[] = ['key' => [$item->created_at, $item->id], 'line' => OrderItemData::from($item)];
        }

        foreach ($order->bundlePurchases as $purchase) {
            /** @var BundlePurchase $purchase */
            $lines[] = ['key' => [$purchase->created_at, $purchase->id], 'line' => BundlePurchaseData::from($purchase)];
        }

        usort($lines, fn (array $left, array $right): int => $left['key'] <=> $right['key']);

        return array_values(array_map(fn (array $entry): array => $entry['line']->toArray(), $lines));
    }
}

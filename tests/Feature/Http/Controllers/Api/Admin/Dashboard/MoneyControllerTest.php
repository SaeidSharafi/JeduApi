<?php

declare(strict_types=1);

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Bundle;
use App\Models\BundlePurchase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;

use function Pest\Laravel\getJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

const MONEY_ENDPOINT = '/api/v1/admin/dashboard/money';

/**
 * A completed order; the caller supplies its grand total so the fixture stays coherent.
 *
 * @param  array<string, mixed>  $attributes
 */
function moneyOrder(int $grandTotal, array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'status'      => OrderStatusEnum::COMPLETED,
        'grand_total' => $grandTotal,
    ], $attributes));
}

/** Record an order payment with the deterministic status the money rules depend on. */
function moneyPayment(
    Order $order,
    PaymentStatusEnum $status = PaymentStatusEnum::COMPLETED,
    PaymentMethodEnum $method = PaymentMethodEnum::BANK_TRANSFER,
): Payment {
    return $order->payments()->create([
        'customer_id' => $order->customer_id,
        'method'      => $method,
        'purpose'     => PaymentPurposeEnum::ORDER,
        'amount'      => $order->grand_total,
        'status'      => $status,
    ]);
}

/** A published delivery option whose product has the requested morph alias. */
function moneyOption(ProductableEnum $type, int $price): ProductDeliveryOption
{
    $product = match ($type) {
        ProductableEnum::COURSE        => Product::factory()->withCourse(),
        ProductableEnum::SEMINAR       => Product::factory()->withSeminar(),
        ProductableEnum::DIGITAL_ASSET => Product::factory()->withDigitalAsset(),
        ProductableEnum::BUNDLE        => Product::factory()->state([
            'productable_type' => ProductableEnum::BUNDLE->value,
            'productable_id'   => Bundle::factory(),
        ]),
    };

    return ProductDeliveryOption::factory()->create([
        'product_id' => $product->create()->id,
        'price'      => $price,
    ]);
}

/**
 * A line item with explicit money columns; `total` is the line total, not a per-unit price.
 */
function moneyLine(
    Order $order,
    ?ProductDeliveryOption $option,
    int $price,
    int $total,
    int $quantity = 1,
    OrderItemPaymentTypeEnum $paymentType = OrderItemPaymentTypeEnum::FULL_PAYMENT,
    array $attributes = [],
): OrderItem {
    return OrderItem::factory()->create(array_merge([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $option?->id,
        'name'                       => $option?->name ?? 'Removed product',
        'sku'                        => $option?->sku  ?? 'MONEY-UNKNOWN',
        'product_data_snapshot_json' => [],
        'price'                      => $price,
        'total'                      => $total,
        'qty_ordered'                => $quantity,
        'payment_type'               => $paymentType,
        'status'                     => OrderItemStatusEnum::COMPLETED,
    ], $attributes));
}

/** A Bundle Purchase row: the bundle's own line, priced at selling_price and owning component items. */
function moneyBundlePurchase(Order $order, int $sellingPrice): BundlePurchase
{
    return BundlePurchase::query()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => moneyOption(ProductableEnum::BUNDLE, 900_000)->id,
        'bundle_name'                => 'Career Bundle',
        'product_name'               => 'Career',
        'name'                       => 'Complete package',
        'sku'                        => 'MONEY-BUNDLE',
        'base_value'                 => $sellingPrice,
        'selling_price'              => $sellingPrice,
        'composition_version'        => 1,
        'checkout_status'            => OrderStatusEnum::PENDING->value,
        'product_data_snapshot_json' => [],
    ]);
}

it('rejects an unauthenticated request', function (): void {
    getJson(MONEY_ENDPOINT)->assertUnauthorized();
});

it('forbids a staff member holding only the dashboard view permission', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    getJson(MONEY_ENDPOINT)->assertForbidden();
});

it('returns the money block envelope for a staff member with the financials permission', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    $response = getJson(MONEY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'payment_mix' => [
                'full'    => ['orders', 'amount', 'share_pct'],
                'deposit' => ['orders', 'amount', 'share_pct'],
            ],
            'product_type_mix' => [
                'course'        => ['orders', 'amount', 'share_pct'],
                'bundle'        => ['orders', 'amount', 'share_pct'],
                'seminar'       => ['orders', 'amount', 'share_pct'],
                'digital_asset' => ['orders', 'amount', 'share_pct'],
                'unknown'       => ['orders', 'amount', 'share_pct'],
            ],
            'free_orders',
            'offline_balance',
        ],
        'metadata' => [
            'generated_at',
            'cached',
            'cache_ttl',
            'timezone',
            'currency',
        ],
    ]);

    $response->assertJsonPath('data.payment_mix.full.orders', 0);
    $response->assertJsonPath('data.payment_mix.full.amount', 0);
    $response->assertJsonPath('data.payment_mix.full.share_pct', 0.0);
    $response->assertJsonPath('data.payment_mix.deposit.orders', 0);
    $response->assertJsonPath('data.payment_mix.deposit.amount', 0);
    $response->assertJsonPath('data.payment_mix.deposit.share_pct', 0.0);
    $response->assertJsonPath('data.product_type_mix.course.orders', 0);
    $response->assertJsonPath('data.product_type_mix.seminar.amount', 0);
    $response->assertJsonPath('data.product_type_mix.digital_asset.amount', 0);
    $response->assertJsonPath('data.product_type_mix.unknown.amount', 0);
    $response->assertJsonPath('data.free_orders', 0);
    $response->assertJsonPath('data.offline_balance', 0);
    $response->assertJsonPath('metadata.cached', false);
    $response->assertJsonPath('metadata.cache_ttl', 60);
    $response->assertJsonPath('metadata.timezone', 'Asia/Tehran');
    $response->assertJsonPath('metadata.currency', 'IRR');
});

it('reports the block generated_at as a Jalali timestamp in Tehran time', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-01-01 12:00:00');

    $response = getJson(MONEY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('metadata.generated_at', '1403-10-12 12:00:00');
});

it('rejects an unknown period with a validation error', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    getJson(MONEY_ENDPOINT.'?period=last-century')->assertUnprocessable();
});

it('splits the paid-order cohort into full settlement and deposit with one-decimal shares', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    $fullOrder = moneyOrder(1_000_000);
    moneyPayment($fullOrder);
    moneyLine($fullOrder, moneyOption(ProductableEnum::COURSE, 1_000_000), 1_000_000, 1_000_000);

    $depositOrder = moneyOrder(200_000);
    moneyPayment($depositOrder);
    moneyLine(
        $depositOrder,
        moneyOption(ProductableEnum::COURSE, 2_000_000),
        2_000_000,
        200_000,
        paymentType: OrderItemPaymentTypeEnum::PRE_PAYMENT,
    );

    $response = getJson(MONEY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.payment_mix.full.orders', 1);
    $response->assertJsonPath('data.payment_mix.full.amount', 1_000_000);
    $response->assertJsonPath('data.payment_mix.full.share_pct', 83.3);
    $response->assertJsonPath('data.payment_mix.deposit.orders', 1);
    $response->assertJsonPath('data.payment_mix.deposit.amount', 200_000);
    $response->assertJsonPath('data.payment_mix.deposit.share_pct', 16.7);
});

it('counts a free order and keeps it out of the payment mix', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    $paidOrder = moneyOrder(500_000);
    moneyPayment($paidOrder);
    moneyLine($paidOrder, moneyOption(ProductableEnum::COURSE, 500_000), 500_000, 500_000);

    $freeOrder = moneyOrder(0);
    moneyPayment($freeOrder, method: PaymentMethodEnum::NO_PAYMENT);
    moneyLine($freeOrder, moneyOption(ProductableEnum::COURSE, 300_000), 300_000, 0);

    $response = getJson(MONEY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.free_orders', 1);
    $response->assertJsonPath('data.payment_mix.full.orders', 1);
    $response->assertJsonPath('data.payment_mix.full.amount', 500_000);
    $response->assertJsonPath('data.payment_mix.full.share_pct', 100.0);
    $response->assertJsonPath('data.payment_mix.deposit.orders', 0);
    $response->assertJsonPath('data.payment_mix.deposit.amount', 0);
});

it('sums the offline remainder of a prepayment line as price times quantity minus its line total', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    $order = moneyOrder(700_000);
    moneyPayment($order);
    moneyLine(
        $order,
        moneyOption(ProductableEnum::COURSE, 1_000_000),
        1_000_000,
        400_000,
        quantity: 2,
        paymentType: OrderItemPaymentTypeEnum::PRE_PAYMENT,
    );
    moneyLine($order, moneyOption(ProductableEnum::SEMINAR, 300_000), 300_000, 300_000);

    $response = getJson(MONEY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.offline_balance', 1_600_000);
    $response->assertJsonPath('data.offline_balance', fn (int $balance): bool => is_int($balance));
});

it('groups the paid cohort by product type with bundle purchases at selling price', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    $courseOrder = moneyOrder(1_000_000);
    moneyPayment($courseOrder);
    moneyLine($courseOrder, moneyOption(ProductableEnum::COURSE, 1_000_000), 1_000_000, 1_000_000);

    $seminarOrder = moneyOrder(500_000);
    moneyPayment($seminarOrder);
    moneyLine($seminarOrder, moneyOption(ProductableEnum::SEMINAR, 500_000), 500_000, 500_000);

    $digitalOrder = moneyOrder(250_000);
    moneyPayment($digitalOrder);
    moneyLine($digitalOrder, moneyOption(ProductableEnum::DIGITAL_ASSET, 250_000), 250_000, 250_000);

    $unknownOrder = moneyOrder(250_000);
    moneyPayment($unknownOrder);
    moneyLine($unknownOrder, null, 250_000, 250_000);

    $bundleOrder = moneyOrder(750_000);
    moneyPayment($bundleOrder);
    $purchase = moneyBundlePurchase($bundleOrder, 750_000);
    moneyLine(
        $bundleOrder,
        moneyOption(ProductableEnum::COURSE, 450_000),
        450_000,
        450_000,
        attributes: ['bundle_purchase_id' => $purchase->id],
    );

    $freeOrder = moneyOrder(0);
    moneyPayment($freeOrder, method: PaymentMethodEnum::NO_PAYMENT);
    moneyLine($freeOrder, moneyOption(ProductableEnum::COURSE, 300_000), 300_000, 0);

    $response = getJson(MONEY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.product_type_mix.course.orders', 1);
    $response->assertJsonPath('data.product_type_mix.course.amount', 1_000_000);
    $response->assertJsonPath('data.product_type_mix.course.share_pct', 36.4);
    $response->assertJsonPath('data.product_type_mix.bundle.orders', 1);
    $response->assertJsonPath('data.product_type_mix.bundle.amount', 750_000);
    $response->assertJsonPath('data.product_type_mix.bundle.share_pct', 27.3);
    $response->assertJsonPath('data.product_type_mix.seminar.orders', 1);
    $response->assertJsonPath('data.product_type_mix.seminar.amount', 500_000);
    $response->assertJsonPath('data.product_type_mix.seminar.share_pct', 18.2);
    $response->assertJsonPath('data.product_type_mix.digital_asset.orders', 1);
    $response->assertJsonPath('data.product_type_mix.digital_asset.amount', 250_000);
    $response->assertJsonPath('data.product_type_mix.digital_asset.share_pct', 9.1);
    $response->assertJsonPath('data.product_type_mix.unknown.orders', 1);
    $response->assertJsonPath('data.product_type_mix.unknown.amount', 250_000);
    $response->assertJsonPath('data.product_type_mix.unknown.share_pct', 9.1);
    $response->assertJsonPath('data.free_orders', 1);
});

it('only counts orders created inside the selected period with a completed payment', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $current = moneyOrder(1_000_000);
    moneyPayment($current);
    moneyLine($current, moneyOption(ProductableEnum::COURSE, 1_000_000), 1_000_000, 1_000_000);

    $old = moneyOrder(9_000_000, ['created_at' => now()->subDays(120)]);
    moneyPayment($old);
    moneyLine($old, moneyOption(ProductableEnum::COURSE, 9_000_000), 9_000_000, 9_000_000);

    $unpaid = moneyOrder(500_000);
    moneyPayment($unpaid, status: PaymentStatusEnum::PENDING);
    moneyLine($unpaid, moneyOption(ProductableEnum::COURSE, 500_000), 500_000, 500_000);

    $response = getJson(MONEY_ENDPOINT.'?period=7d');

    $response->assertOk();
    $response->assertJsonPath('data.payment_mix.full.orders', 1);
    $response->assertJsonPath('data.payment_mix.full.amount', 1_000_000);
    $response->assertJsonPath('data.product_type_mix.course.orders', 1);
    $response->assertJsonPath('data.product_type_mix.course.amount', 1_000_000);
    $response->assertJsonPath('data.free_orders', 0);
});

it('serves the cached block until fresh=1 recomputes it', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $firstOrder = moneyOrder(1_000_000);
    moneyPayment($firstOrder);
    moneyLine($firstOrder, moneyOption(ProductableEnum::COURSE, 1_000_000), 1_000_000, 1_000_000);

    $first = getJson(MONEY_ENDPOINT);
    $first->assertOk();
    $first->assertJsonPath('data.payment_mix.full.amount', 1_000_000);
    $first->assertJsonPath('metadata.cached', false);
    $first->assertJsonPath('metadata.generated_at', '1404-06-05 12:00:00');

    $secondOrder = moneyOrder(2_000_000);
    moneyPayment($secondOrder);
    moneyLine($secondOrder, moneyOption(ProductableEnum::COURSE, 2_000_000), 2_000_000, 2_000_000);
    // The block TTL is 60 s and the array store treats the expiry instant as expired, so hop 30 s.
    $this->travel(30)->seconds();

    $cached = getJson(MONEY_ENDPOINT);
    $cached->assertOk();
    $cached->assertJsonPath('data.payment_mix.full.amount', 1_000_000);
    $cached->assertJsonPath('metadata.cached', true);
    $cached->assertJsonPath('metadata.generated_at', '1404-06-05 12:00:00');

    $fresh = getJson(MONEY_ENDPOINT.'?fresh=1');
    $fresh->assertOk();
    $fresh->assertJsonPath('data.payment_mix.full.amount', 3_000_000);
    $fresh->assertJsonPath('metadata.cached', false);
    $fresh->assertJsonPath('metadata.generated_at', '1404-06-05 12:00:30');
});

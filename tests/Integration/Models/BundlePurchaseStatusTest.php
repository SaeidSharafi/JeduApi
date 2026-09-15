<?php

declare(strict_types=1);

use App\Enums\BundlePurchaseStatusEnum;
use App\Enums\EnrollmentRevocationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Models\BundlePurchase;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Models\User;

use function Pest\Laravel\assertDatabaseCount;

covers(BundlePurchase::class);

/** @param  array<int, array{status: OrderItemStatusEnum, enrollment_status?: EnrollmentStatusEnum, provisioning_status?: ProvisioningStatusEnum, revocation_status?: EnrollmentRevocationStatusEnum, no_enrollment?: bool}>  $components */
function bundlePurchaseWithComponents(array $components, bool $paid = true, bool $orderCancelled = false): BundlePurchase
{
    $customer = User::factory()->create();
    $order    = Order::factory()->create([
        'customer_id' => $customer->id,
        'status'      => $orderCancelled ? OrderStatusEnum::CANCELLED : OrderStatusEnum::COMPLETED,
        'grand_total' => 100000,
    ]);
    if ($paid) {
        Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $customer->id,
            'amount'      => 100000,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);
    }
    $parentOption = ProductDeliveryOption::factory()->create();
    $purchase     = BundlePurchase::query()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $parentOption->id,
        'bundle_name'                => 'Career Bundle', 'product_name' => 'Career', 'name' => 'Complete package',
        'sku'                        => 'BUNDLE-1', 'base_value' => 200000, 'selling_price' => 100000,
        'composition_version'        => 1, 'checkout_status' => OrderStatusEnum::PENDING->value,
        'product_data_snapshot_json' => [],
    ]);

    foreach ($components as $index => $config) {
        $option = ProductDeliveryOption::factory()->create(['price' => 100000]);
        $item   = OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'bundle_purchase_id'         => $purchase->id,
            'product_delivery_option_id' => $option->id,
            'status'                     => $config['status'],
            'price'                      => 100000,
            'total'                      => $config['status'] === OrderItemStatusEnum::PENDING ? 0 : 100000,
            'pricing_metadata'           => ['paid_amount' => 50000, 'total_discount_amount' => 50000],
        ]);
        if (($config['no_enrollment'] ?? false) === true) {
            continue;
        }
        Enrollment::factory()->create([
            'order_id'                   => $order->id,
            'order_item_id'              => $item->id,
            'customer_id'                => $customer->id,
            'product_delivery_option_id' => $option->id,
            'enrollment_status'          => ($config['enrollment_status'] ?? EnrollmentStatusEnum::ACTIVE)->value,
        ])->update([
            'provisioning_status' => ($config['provisioning_status'] ?? ProvisioningStatusEnum::HEALTHY)->value,
            'revocation_status'   => ($config['revocation_status'] ?? null)?->value,
        ]);
    }

    return $purchase->fresh();
}

it('derives pending_payment while the order has no completed payment', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::PENDING, 'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT],
    ], paid: false);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::PENDING_PAYMENT);
});

it('derives cancelled for an unpaid order cancelled before payment', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::PENDING, 'enrollment_status' => EnrollmentStatusEnum::CANCELLED],
    ], paid: false, orderCancelled: true);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::CANCELLED);
});

it('derives active when every component is healthy', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED],
        ['status' => OrderItemStatusEnum::COMPLETED],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::ACTIVE);
});

it('derives provisioning while any component is still provisioning', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED, 'provisioning_status' => ProvisioningStatusEnum::HEALTHY],
        ['status' => OrderItemStatusEnum::COMPLETED, 'provisioning_status' => ProvisioningStatusEnum::IN_PROGRESS],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::PROVISIONING);
});

it('derives partially_failed when one component fails and another stays active', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED, 'provisioning_status' => ProvisioningStatusEnum::HEALTHY],
        ['status' => OrderItemStatusEnum::COMPLETED, 'provisioning_status' => ProvisioningStatusEnum::DEGRADED],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::PARTIALLY_FAILED);
});

it('derives failed when every component provisioning failed', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED, 'provisioning_status' => ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED],
        ['status' => OrderItemStatusEnum::COMPLETED, 'provisioning_status' => ProvisioningStatusEnum::DEGRADED],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::FAILED);
});

it('derives refunded when every component item is refunded and revoked', function (): void {
    $purchase = bundlePurchaseWithComponents([
        [
            'status'            => OrderItemStatusEnum::REFUNDED, 'enrollment_status' => EnrollmentStatusEnum::CANCELLED,
            'revocation_status' => EnrollmentRevocationStatusEnum::REVOKED,
        ],
        [
            'status'            => OrderItemStatusEnum::REFUNDED, 'enrollment_status' => EnrollmentStatusEnum::CANCELLED,
            'revocation_status' => EnrollmentRevocationStatusEnum::REVOKED,
        ],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::REFUNDED);
});

it('derives revocation_pending when a refunded component has no revocation record', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::REFUNDED, 'enrollment_status' => EnrollmentStatusEnum::CANCELLED],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::REVOCATION_PENDING);
});

it('keeps one successful component active while another is failed', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED, 'provisioning_status' => ProvisioningStatusEnum::HEALTHY],
        ['status' => OrderItemStatusEnum::COMPLETED, 'provisioning_status' => ProvisioningStatusEnum::DEGRADED],
    ]);

    $successful = $purchase->components->first(
        fn (OrderItem $component): bool => $component->enrollment?->provisioning_status === ProvisioningStatusEnum::HEALTHY
    );

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::PARTIALLY_FAILED)
        ->and($successful->enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE);

    assertDatabaseCount('enrollments', 2);
});

it('derives active for a single healthy component', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::ACTIVE);
});

it('derives provisioning while a paid component item is still pending', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::PENDING, 'enrollment_status' => EnrollmentStatusEnum::ACTIVE],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::PROVISIONING);
});

it('derives provisioning when a completed component has no Enrollment', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED, 'no_enrollment' => true],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::PROVISIONING);
});

it('derives provisioning for a paid purchase with no component lines', function (): void {
    $purchase = bundlePurchaseWithComponents([]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::PROVISIONING);
});

it('derives cancelled when every paid component item is cancelled', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::CANCELLED, 'enrollment_status' => EnrollmentStatusEnum::CANCELLED],
        ['status' => OrderItemStatusEnum::CANCELLED, 'enrollment_status' => EnrollmentStatusEnum::CANCELLED],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::CANCELLED);
});

it('derives provisioning when a failed component coexists with a pending one', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED, 'provisioning_status' => ProvisioningStatusEnum::DEGRADED],
        ['status' => OrderItemStatusEnum::PENDING, 'enrollment_status' => EnrollmentStatusEnum::ACTIVE],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::PROVISIONING);
});

it('does not report a fully suspended purchase as active', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED, 'enrollment_status' => EnrollmentStatusEnum::SUSPENDED],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::FAILED);
});

it('derives provisioning when a completed component is still awaiting payment on its Enrollment', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED, 'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::PROVISIONING);
});

it('persists every commercial fillable field when a Bundle Purchase is created', function (): void {
    $customer = User::factory()->create();
    $order    = Order::factory()->create(['customer_id' => $customer->id, 'grand_total' => 100000]);
    $option   = ProductDeliveryOption::factory()->create();
    $data     = [
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $option->id,
        'bundle_name'                => 'Career Bundle', 'product_name' => 'Career', 'name' => 'Complete package',
        'sku'                        => 'BUNDLE-FILL', 'base_value' => 200000, 'selling_price' => 100000,
        'composition_version'        => 1, 'checkout_status' => 'pending',
        'product_data_snapshot_json' => ['bundle' => true],
    ];

    $purchase = BundlePurchase::query()->create($data);

    $fresh = $purchase->fresh();
    expect($fresh->order_id)->toBe($order->id)
        ->and($fresh->product_delivery_option_id)->toBe($option->id)
        ->and($fresh->bundle_name)->toBe('Career Bundle')
        ->and($fresh->product_name)->toBe('Career')
        ->and($fresh->name)->toBe('Complete package')
        ->and($fresh->sku)->toBe('BUNDLE-FILL')
        ->and($fresh->base_value)->toBe(200000)
        ->and($fresh->selling_price)->toBe(100000)
        ->and($fresh->composition_version)->toBe(1)
        ->and($fresh->checkout_status)->toBe(OrderStatusEnum::PENDING)
        ->and($fresh->product_data_snapshot_json)->toBe(['bundle' => true]);
});

it('derives cancelled from an unpaid cancelled order even while components stay active', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::PENDING, 'enrollment_status' => EnrollmentStatusEnum::ACTIVE],
    ], paid: false, orderCancelled: true);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::CANCELLED);
});

it('derives cancelled when every unpaid component item is cancelled regardless of order status', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::CANCELLED, 'enrollment_status' => EnrollmentStatusEnum::ACTIVE],
    ], paid: false, orderCancelled: false);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::CANCELLED);
});

it('derives cancelled when every unpaid component Enrollment is cancelled even with pending items', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::PENDING, 'enrollment_status' => EnrollmentStatusEnum::CANCELLED],
    ], paid: false, orderCancelled: false);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::CANCELLED);
});

it('derives failed for a single failed component after payment', function (): void {
    $purchase = bundlePurchaseWithComponents([
        ['status' => OrderItemStatusEnum::COMPLETED, 'provisioning_status' => ProvisioningStatusEnum::DEGRADED],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::FAILED);
});

it('derives revocation_pending while a refunded component is not yet revoked', function (): void {
    $purchase = bundlePurchaseWithComponents([
        [
            'status'            => OrderItemStatusEnum::REFUNDED, 'enrollment_status' => EnrollmentStatusEnum::SUSPENDED,
            'revocation_status' => EnrollmentRevocationStatusEnum::PENDING,
        ],
        [
            'status'            => OrderItemStatusEnum::REFUNDED, 'enrollment_status' => EnrollmentStatusEnum::SUSPENDED,
            'revocation_status' => EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED,
        ],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::REVOCATION_PENDING);
});

it('derives refunded only once every component revocation succeeded', function (): void {
    $purchase = bundlePurchaseWithComponents([
        [
            'status'            => OrderItemStatusEnum::REFUNDED, 'enrollment_status' => EnrollmentStatusEnum::CANCELLED,
            'revocation_status' => EnrollmentRevocationStatusEnum::REVOKED,
        ],
        [
            'status'            => OrderItemStatusEnum::REFUNDED, 'enrollment_status' => EnrollmentStatusEnum::CANCELLED,
            'revocation_status' => EnrollmentRevocationStatusEnum::REVOKED,
        ],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::REFUNDED);
});

it('keeps revocation_pending when one component is revoked and another still blocked', function (): void {
    $purchase = bundlePurchaseWithComponents([
        [
            'status'            => OrderItemStatusEnum::REFUNDED, 'enrollment_status' => EnrollmentStatusEnum::CANCELLED,
            'revocation_status' => EnrollmentRevocationStatusEnum::REVOKED,
        ],
        [
            'status'            => OrderItemStatusEnum::REFUNDED, 'enrollment_status' => EnrollmentStatusEnum::SUSPENDED,
            'revocation_status' => EnrollmentRevocationStatusEnum::FAILED,
        ],
    ]);

    expect($purchase->status)->toBe(BundlePurchaseStatusEnum::REVOCATION_PENDING);
});
/*
 * Mutation notes (pest --mutate --parallel):
 * Runs consistently score ~85% on this file. The surviving mutants cluster on
 * the BundlePurchase $fillable array (RemoveArrayItem) and on early-return /
 * disjunct branches whose alternatives produce an identical derived status for
 * every reachable input (e.g. all-REFUNDED is returned both by the dedicated
 * guard and by the inactive-components fallback). See issue #13 acceptance
 * criteria.
 */

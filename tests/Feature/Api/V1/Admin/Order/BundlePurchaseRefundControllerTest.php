<?php

declare(strict_types=1);

use App\Enums\BundlePurchaseStatusEnum;
use App\Enums\EnrollmentRevocationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Events\RefundCompletedEvent;
use App\Jobs\Provisioning\RevokeEnrollmentProviderJob;
use App\Models\BundlePurchase;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\ProvisioningAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\postJson;

/**
 * @param  list<array{base: int, paid: int, provider: string}>  $components
 * @return array{purchase: BundlePurchase, order: Order, items: list<OrderItem>, enrollments: list<Enrollment>}
 */
function bundleHttpPurchase(array $components = [['base' => 100000, 'paid' => 100000, 'provider' => 'moodle']]): array
{
    $customer = User::factory()->create();
    $paid     = array_sum(array_column($components, 'paid'));
    $order    = Order::factory()->create([
        'customer_id' => $customer->id, 'status' => OrderStatusEnum::COMPLETED, 'grand_total' => $paid,
    ]);
    $order->payments()->create([
        'customer_id' => $customer->id, 'method' => PaymentMethodEnum::BANK_TRANSFER,
        'amount'      => $paid, 'status' => PaymentStatusEnum::COMPLETED,
    ]);

    $purchase = BundlePurchase::query()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => ProductDeliveryOption::factory()->create()->id,
        'bundle_name'                => 'Career Bundle',
        'product_name'               => 'Career',
        'name'                       => 'Complete package',
        'sku'                        => 'BUNDLE-HTTP',
        'base_value'                 => array_sum(array_column($components, 'base')),
        'selling_price'              => $paid,
        'composition_version'        => 1,
        'checkout_status'            => OrderStatusEnum::PENDING->value,
        'product_data_snapshot_json' => [],
    ]);

    $items       = [];
    $enrollments = [];
    foreach ($components as $component) {
        $provider = $component['provider'];
        $option   = ProductDeliveryOption::factory()->create(['price' => $component['base']]);
        $item     = OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'bundle_purchase_id'         => $purchase->id,
            'product_delivery_option_id' => $option->id,
            'status'                     => OrderItemStatusEnum::COMPLETED,
            'price'                      => $component['base'],
            'total'                      => $component['paid'],
            'qty_ordered'                => 1,
            'pricing_metadata'           => [
                'original_price' => $component['base'], 'base_price_amount' => $component['base'],
                'paid_amount'    => $component['paid'], 'total_discount_amount' => $component['base'] - $component['paid'],
            ],
        ]);
        $enrollment = Enrollment::factory()->create([
            'order_item_id'              => $item->id,
            'order_id'                   => $order->id,
            'customer_id'                => $customer->id,
            'product_delivery_option_id' => $option->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);
        $enrollment->update([
            'provisioning_plan' => [
                'version'   => 1, 'status' => 'healthy', 'resolved_at' => now()->toISOString(),
                'providers' => [[
                    'provider' => $provider, 'applicable' => true, 'readiness' => 'ready', 'configuration_issue' => null,
                ]],
            ],
            'provisioning_data' => ['providers' => [$provider => ['status' => 'success', 'data' => [
                'moodle_user_id' => 5, 'moodle_course_id' => 6, 'ims_student_id' => 7, 'ims_enrollment_id' => 8,
            ]]]],
            'provisioning_status' => ProvisioningStatusEnum::HEALTHY,
        ]);

        $items[]       = $item->fresh();
        $enrollments[] = $enrollment->fresh();
    }

    return [
        'purchase' => $purchase->fresh(), 'order' => $order->fresh(), 'items' => $items, 'enrollments' => $enrollments,
    ];
}

beforeEach(function (): void {
    Event::fake([RefundCompletedEvent::class]);
    Queue::fake([RevokeEnrollmentProviderJob::class]);
});

it('refunds a Bundle Purchase and returns the financial and revocation breakdown', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);
    $scenario = bundleHttpPurchase([
        ['base' => 120000, 'paid' => 100000, 'provider' => 'moodle'],
        ['base' => 80000, 'paid' => 0, 'provider' => 'moodle'],
    ]);

    postJson(route('api.v1.admin.bundle-purchases.refund', ['bundlePurchase' => $scenario['purchase']->id]), [
        'deduction_percent' => 10,
    ])
        ->assertCreated()
        ->assertJsonPath('data.bundle_purchase_id', $scenario['purchase']->id)
        ->assertJsonPath('data.base_value', 200000)
        ->assertJsonPath('data.paid_amount', 100000)
        ->assertJsonPath('data.policy_deduction_amount', 20000)
        ->assertJsonPath('data.effective_deduction_amount', 20000)
        ->assertJsonPath('data.refund_amount', 80000)
        ->assertJsonPath('data.status.value', BundlePurchaseStatusEnum::REVOCATION_PENDING->value)
        ->assertJsonCount(2, 'data.components')
        ->assertJsonPath('data.components.0.refund_amount', 80000)
        ->assertJsonPath('data.components.0.revocation_status.value', EnrollmentRevocationStatusEnum::PENDING->value)
        ->assertJsonPath('data.components.1.refund_amount', 0);

    Queue::assertPushed(RevokeEnrollmentProviderJob::class, 2);
});

it('forbids refunding a Bundle Purchase without the refund permission', function (): void {
    $this->unauthorized_user();
    $scenario = bundleHttpPurchase();

    postJson(route('api.v1.admin.bundle-purchases.refund', ['bundlePurchase' => $scenario['purchase']->id]), [])
        ->assertForbidden();

    assertDatabaseCount('refunds', 0);
});

it('rejects an ordinary item refund for an internal Bundle component', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);
    $scenario = bundleHttpPurchase();

    postJson(route('api.v1.admin.refunds.store'), [
        'order_item_id'       => $scenario['items'][0]->id,
        'deduction_amount'    => 0,
        'status'              => RefundStatusEnum::PENDING->value,
        'transaction_details' => [
            'receiver_name' => 'Ali Rezaei',
            'card_number'   => '1234567890123456',
            'iban_number'   => 'IR123456789012345678901234',
        ],
    ])->assertUnprocessable()
        ->assertJsonPath('errors.order_item_id.0', __('messages.order.refund.bundle_component_requires_bundle_refund'));

    assertDatabaseCount('refunds', 0);
});

it('refunds a Bundle Purchase through the full-order refund endpoint', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);
    $scenario = bundleHttpPurchase();

    postJson(route('api.v1.admin.orders.refund', ['order' => $scenario['order']->id]))
        ->assertCreated()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status.value', RefundStatusEnum::COMPLETED->value)
        ->assertJsonPath('data.0.deduction_amount', 0);

    assertDatabaseCount('refunds', 1);
    $this->assertDatabaseHas('refunds', [
        'order_item_id' => $scenario['items'][0]->id,
        'amount'        => 100000,
    ]);
    $this->assertDatabaseHas('order_items', [
        'id'     => $scenario['items'][0]->id,
        'status' => OrderItemStatusEnum::REFUNDED->value,
    ]);
    $this->assertDatabaseHas('enrollments', [
        'id'                => $scenario['enrollments'][0]->id,
        'revocation_status' => EnrollmentRevocationStatusEnum::PENDING->value,
    ]);
});

it('retries only the outstanding component revocation of a Bundle Purchase', function (): void {
    $this->authorized_user([PermissionEnum::REFUND_CREATE]);
    $scenario   = bundleHttpPurchase();
    $enrollment = $scenario['enrollments'][0];
    $enrollment->update([
        'enrollment_status' => EnrollmentStatusEnum::SUSPENDED,
        'revocation_status' => EnrollmentRevocationStatusEnum::FAILED,
    ]);
    ProvisioningAttempt::query()->create([
        'enrollment_id' => $enrollment->id, 'provider' => ProvisioningProviderEnum::MOODLE,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION,
        'status'        => ProvisioningAttemptStatusEnum::FAILED, 'sequence' => 1, 'retryable' => true,
        'failed_at'     => now(), 'failure_metadata' => ['kind' => 'revocation'],
    ]);

    postJson(route('api.v1.admin.bundle-purchases.retry-revocation', ['bundlePurchase' => $scenario['purchase']->id]))
        ->assertOk()
        ->assertJsonPath('data.0.revocation_status.value', EnrollmentRevocationStatusEnum::PENDING->value);

    Queue::assertPushed(RevokeEnrollmentProviderJob::class, 1);
    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION->value,
        'status'        => ProvisioningAttemptStatusEnum::QUEUED->value,
    ]);
});

it('manually confirms an unsupported component revocation and releases eligibility', function (): void {
    $this->authorized_user([PermissionEnum::ENROLLMENT_WAIVE_PROVISION]);
    $scenario   = bundleHttpPurchase([['base' => 100000, 'paid' => 100000, 'provider' => 'ims']]);
    $enrollment = $scenario['enrollments'][0];
    $enrollment->update([
        'enrollment_status' => EnrollmentStatusEnum::SUSPENDED,
        'revocation_status' => EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED,
    ]);

    postJson(route('api.v1.admin.enrollments.confirm-revocation', ['enrollment' => $enrollment->id]), [
        'reason' => 'Removed manually in IMS',
    ])
        ->assertOk()
        ->assertJsonPath('data.revocation_status.value', EnrollmentRevocationStatusEnum::REVOKED->value)
        ->assertJsonPath('data.enrollment_status.value', EnrollmentStatusEnum::CANCELLED->value);

    $this->assertDatabaseHas('enrollments', [
        'id'                => $enrollment->id,
        'revocation_status' => EnrollmentRevocationStatusEnum::REVOKED->value,
        'enrollment_status' => EnrollmentStatusEnum::CANCELLED->value,
    ]);
});

it('forbids revoking without the enrollment permission', function (): void {
    $this->unauthorized_user();
    $scenario   = bundleHttpPurchase();
    $enrollment = $scenario['enrollments'][0];

    postJson(route('api.v1.admin.enrollments.retry-revocation', ['enrollment' => $enrollment->id]))
        ->assertForbidden();
    postJson(route('api.v1.admin.enrollments.confirm-revocation', ['enrollment' => $enrollment->id]))
        ->assertForbidden();
});

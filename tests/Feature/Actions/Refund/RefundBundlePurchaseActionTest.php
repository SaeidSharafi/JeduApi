<?php

declare(strict_types=1);

use App\Actions\Admin\Refund\RefundBundlePurchaseAction;
use App\Data\Admin\Refund\RefundBundlePurchaseData;
use App\Enums\BundlePurchaseStatusEnum;
use App\Enums\EnrollmentRevocationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Events\RefundCompletedEvent;
use App\Exceptions\RefundValidationException;
use App\Jobs\Provisioning\RevokeEnrollmentProviderJob;
use App\Models\BundlePurchase;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payment\Digipay\Data\RefundResponse;
use App\Services\Payment\Digipay\DigipayAdminService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

use function Pest\Laravel\assertDatabaseCount;

covers(RefundBundlePurchaseAction::class);

/**
 * @param  array{provider?: string}  $options
 */
function bundleRefundComponent(
    BundlePurchase $purchase,
    Order $order,
    int $basePrice,
    int $paidAmount,
    array $options = [],
): OrderItem {
    $provider = $options['provider'] ?? 'moodle';
    $option   = ProductDeliveryOption::factory()->create(['price' => $basePrice]);
    $item     = OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'bundle_purchase_id'         => $purchase->id,
        'product_delivery_option_id' => $option->id,
        'status'                     => OrderItemStatusEnum::COMPLETED,
        'price'                      => $basePrice,
        'total'                      => $paidAmount,
        'qty_ordered'                => 1,
        'pricing_metadata'           => [
            'original_price'        => $basePrice,
            'base_price_amount'     => $basePrice,
            'paid_amount'           => $paidAmount,
            'total_discount_amount' => $basePrice - $paidAmount,
            'discount_amount'       => $basePrice - $paidAmount,
        ],
    ]);

    $enrollment = Enrollment::factory()->create([
        'order_id'                   => $order->id,
        'order_item_id'              => $item->id,
        'customer_id'                => $order->customer_id,
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);
    $enrollment->update([
        'provisioning_plan' => [
            'version'   => 1,
            'providers' => [[
                'provider' => $provider, 'applicable' => true, 'readiness' => 'ready', 'configuration_issue' => null,
            ]],
            'status'      => 'healthy',
            'resolved_at' => now()->toISOString(),
        ],
        'provisioning_data' => [
            'providers' => [
                $provider => [
                    'status' => 'success',
                    'data'   => $provider === 'moodle'
                        ? ['moodle_user_id' => 7, 'moodle_course_id' => 9]
                        : ['ims_student_id' => 7, 'ims_enrollment_id' => 9],
                ],
            ],
        ],
        'provisioning_status' => ProvisioningStatusEnum::HEALTHY,
    ]);

    return $item->fresh();
}

/**
 * @param  list<array{base: int, paid: int, provider?: string}>  $components
 * @return array{purchase: BundlePurchase, order: Order, items: list<OrderItem>, enrollments: list<Enrollment>}
 */
function bundleRefundScenario(
    array $components,
    PaymentMethodEnum $method = PaymentMethodEnum::BANK_TRANSFER,
    bool $withStandalone = false,
): array {
    $customer = User::factory()->create();
    $grand    = array_sum(array_column($components, 'paid'));
    $order    = Order::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => OrderStatusEnum::COMPLETED,
        'grand_total'            => $grand,
        'full_value_grand_total' => $grand,
    ]);
    $order->payments()->create([
        'customer_id' => $customer->id,
        'method'      => $method,
        'amount'      => $grand,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $parent   = ProductDeliveryOption::factory()->create();
    $purchase = BundlePurchase::query()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $parent->id,
        'bundle_name'                => 'Career Bundle',
        'product_name'               => 'Career',
        'name'                       => 'Complete package',
        'sku'                        => 'BUNDLE-REFUND',
        'base_value'                 => array_sum(array_column($components, 'base')),
        'selling_price'              => $grand,
        'composition_version'        => 1,
        'checkout_status'            => OrderStatusEnum::PENDING->value,
        'product_data_snapshot_json' => [],
    ]);

    $items       = [];
    $enrollments = [];
    foreach ($components as $component) {
        $item          = bundleRefundComponent($purchase, $order, $component['base'], $component['paid'], $component);
        $items[]       = $item;
        $enrollments[] = $item->enrollment;
    }

    if ($withStandalone) {
        $standalone = ProductDeliveryOption::factory()->create(['price' => 50000]);
        OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $standalone->id,
            'status'                     => OrderItemStatusEnum::COMPLETED,
            'price'                      => 50000,
            'total'                      => 50000,
            'qty_ordered'                => 1,
            'pricing_metadata'           => ['original_price' => 50000, 'base_price_amount' => 50000, 'paid_amount' => 50000],
        ]);
    }

    return [
        'purchase'    => $purchase->fresh(),
        'order'       => $order->fresh(),
        'items'       => $items,
        'enrollments' => $enrollments,
    ];
}

beforeEach(function (): void {
    Event::fake([RefundCompletedEvent::class]);
    Queue::fake([RevokeEnrollmentProviderJob::class]);
});

it('refunds every component as one weighted operation and starts revocation', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 120000, 'paid' => 100000],
        ['base' => 80000, 'paid' => 0],
    ]);

    $result = app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(deduction_percent: 10, admin_notes: 'Bundle refund'),
    );

    // 10% of the 200,000 base value = 20,000, weighted 120k/80k, then the
    // 8,000 that landed on the zero-paid component is redistributed.
    expect($result->policy_deduction_amount)->toBe(20000)
        ->and($result->effective_deduction_amount)->toBe(20000)
        ->and($result->refund_amount)->toBe(80000)
        ->and($result->status)->toBe(BundlePurchaseStatusEnum::REVOCATION_PENDING)
        ->and($result->components)->toHaveCount(2)
        ->and($result->components[0]->policy_deduction_amount)->toBe(12000)
        ->and($result->components[0]->effective_deduction_amount)->toBe(20000)
        ->and($result->components[0]->redistributed_amount)->toBe(8000)
        ->and($result->components[0]->refund_amount)->toBe(80000)
        ->and($result->components[1]->policy_deduction_amount)->toBe(8000)
        ->and($result->components[1]->effective_deduction_amount)->toBe(0)
        ->and($result->components[1]->redistributed_amount)->toBe(-8000)
        ->and($result->components[1]->refund_amount)->toBe(0)
        ->and($result->components[0]->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING)
        ->and($result->components[1]->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING);

    assertDatabaseCount('refunds', 2);
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $scenario['items'][0]->id,
        'amount'           => 80000,
        'deduction_amount' => 20000,
        'status'           => RefundStatusEnum::COMPLETED->value,
    ]);
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $scenario['items'][1]->id,
        'amount'           => 0,
        'deduction_amount' => 0,
        'status'           => RefundStatusEnum::COMPLETED->value,
    ]);
    $this->assertDatabaseHas('order_items', [
        'id'             => $scenario['items'][0]->id,
        'status'         => OrderItemStatusEnum::REFUNDED->value,
        'total_refunded' => 80000,
    ]);
    $this->assertDatabaseHas('enrollments', [
        'id'                => $scenario['enrollments'][0]->id,
        'enrollment_status' => EnrollmentStatusEnum::SUSPENDED->value,
        'revocation_status' => EnrollmentRevocationStatusEnum::PENDING->value,
    ]);
    $this->assertDatabaseHas('enrollments', [
        'id'                => $scenario['enrollments'][1]->id,
        'enrollment_status' => EnrollmentStatusEnum::SUSPENDED->value,
        'revocation_status' => EnrollmentRevocationStatusEnum::PENDING->value,
    ]);

    Event::assertDispatched(RefundCompletedEvent::class, 2);
    Queue::assertPushed(RevokeEnrollmentProviderJob::class, 2);
});

it('applies a fixed Bundle deduction with the same base-price weighting', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 100000, 'paid' => 100000],
        ['base' => 300000, 'paid' => 300000],
    ]);

    $result = app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(deduction_amount: 40000),
    );

    expect($result->components[0]->effective_deduction_amount)->toBe(10000)
        ->and($result->components[1]->effective_deduction_amount)->toBe(30000)
        ->and($result->refund_amount)->toBe(360000);
});

it('caps the effective deduction at the amount actually paid', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 200000, 'paid' => 50000],
    ]);

    $result = app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(deduction_percent: 50),
    );

    expect($result->policy_deduction_amount)->toBe(100000)
        ->and($result->effective_deduction_amount)->toBe(50000)
        ->and($result->refund_amount)->toBe(0);
});

it('leaves unrelated standalone units untouched and reports a partially refunded order', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 100000, 'paid' => 100000],
    ], withStandalone: true);
    $standaloneItem = $scenario['order']->standaloneItems()->sole();

    app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(),
    );

    $this->assertDatabaseHas('order_items', [
        'id'     => $standaloneItem->id,
        'status' => OrderItemStatusEnum::COMPLETED->value,
    ]);
    $this->assertDatabaseCount('refunds', 1);
    $this->assertDatabaseHas('orders', [
        'id'     => $scenario['order']->id,
        'status' => OrderStatusEnum::PARTIALLY_REFUNDED->value,
    ]);
});

it('completes exactly one gateway refund for the whole Bundle and stores its tracking code', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 120000, 'paid' => 100000],
        ['base' => 80000, 'paid' => 0],
    ], PaymentMethodEnum::DIGIPAY);

    $this->mock(DigipayAdminService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->with(Mockery::type(App\Models\Payment::class), 100000)
            ->andReturn(new RefundResponse(statusCode: 0, message: 'OK', trackingCode: 'DGP-BUNDLE'));
    });

    app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(),
    );

    expect(Refund::query()->get()->map(
        fn (Refund $refund): ?string => $refund->transaction_details['gateway_tracking_code'] ?? null
    )->all())->toBe(['DGP-BUNDLE', 'DGP-BUNDLE']);
});

it('does not refund, suspend access, or start revocation when the gateway fails', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 100000, 'paid' => 100000],
    ], PaymentMethodEnum::DIGIPAY);

    $this->mock(DigipayAdminService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refund')->andThrow(new App\Exceptions\Gateway\DigipayException('Gateway down', 500));
    });

    expect(fn () => app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(),
    ))->toThrow(RefundValidationException::class);

    $this->assertDatabaseHas('refunds', ['status' => RefundStatusEnum::FAILED->value]);
    $this->assertDatabaseHas('order_items', [
        'id'     => $scenario['items'][0]->id,
        'status' => OrderItemStatusEnum::COMPLETED->value,
    ]);
    $this->assertDatabaseHas('enrollments', [
        'id'                => $scenario['enrollments'][0]->id,
        'enrollment_status' => EnrollmentStatusEnum::ACTIVE->value,
        'revocation_status' => null,
    ]);
    Queue::assertNothingPushed();
});

it('appends the gateway-skipped note when skip_gateway is requested', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 100000, 'paid' => 100000],
    ]);

    app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(skip_gateway: true),
    );

    $refund = Refund::query()->sole();
    expect($refund->admin_notes)->toContain('Gateway skipped by Admin');
});

it('rejects a Bundle refund when any component was already refunded', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 100000, 'paid' => 100000],
        ['base' => 100000, 'paid' => 100000],
    ]);
    $scenario['items'][0]->update(['status' => OrderItemStatusEnum::REFUNDED]);

    expect(fn () => app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(),
    ))->toThrow(RefundValidationException::class, __('messages.order.refund.already_refunded'));

    assertDatabaseCount('refunds', 0);
});

it('rejects a Bundle refund when any component already has an active refund request', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 100000, 'paid' => 100000],
        ['base' => 100000, 'paid' => 100000],
    ]);
    Refund::factory()->create([
        'order_item_id' => $scenario['items'][0]->id,
        'status'        => RefundStatusEnum::PENDING,
    ]);

    expect(fn () => app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(),
    ))->toThrow(RefundValidationException::class, __('messages.order.refund.refund_request_exists'));

    assertDatabaseCount('refunds', 1);
});

it('rejects a Digipay Bundle refund inside a mixed order when partial refunds are disabled', function (): void {
    config()->set('payments.digipay.allow_partial_refund', false);
    $scenario = bundleRefundScenario([
        ['base' => 100000, 'paid' => 100000],
    ], PaymentMethodEnum::DIGIPAY, withStandalone: true);

    expect(fn () => app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(),
    ))->toThrow(RefundValidationException::class, __('messages.order.refund.digipay_partial_refund_not_supported'));

    assertDatabaseCount('refunds', 0);
});

it('rejects a deduction percent and amount that disagree', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 100000, 'paid' => 100000],
    ]);

    expect(fn () => app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(deduction_amount: 15000, deduction_percent: 10),
    ))->toThrow(RefundValidationException::class, __('messages.order.refund.deduction_conflict'));
});

it('records the redistribution audit on each component refund', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 120000, 'paid' => 100000],
        ['base' => 80000, 'paid' => 0],
    ]);

    app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(deduction_percent: 10),
    );

    $refund = Refund::query()->where('order_item_id', $scenario['items'][0]->id)->sole();
    expect($refund->transaction_details['bundle_refund'])->toMatchArray([
        'bundle_purchase_id'            => $scenario['purchase']->id,
        'policy_deduction_amount'       => 20000,
        'effective_deduction_amount'    => 20000,
        'policy_component_deduction'    => 12000,
        'effective_component_deduction' => 20000,
        'redistributed_amount'          => 8000,
    ]);
});

it('does not double count the bundle purchase against running order totals', function (): void {
    $scenario = bundleRefundScenario([
        ['base' => 100000, 'paid' => 60000],
        ['base' => 100000, 'paid' => 40000],
    ]);

    $result = app(RefundBundlePurchaseAction::class)->handle(
        $scenario['purchase'],
        new RefundBundlePurchaseData(),
    );

    expect($result->refund_amount)->toBe(100000)
        ->and($scenario['order']->fresh()->total_refunded)->toBe(100000);
    expect((int) DB::table('order_items')->where('bundle_purchase_id', $scenario['purchase']->id)->sum('total'))
        ->toBe(100000);
});

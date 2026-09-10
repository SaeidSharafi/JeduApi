<?php

declare(strict_types=1);

use App\Actions\Admin\Refund\RefundOrderAction;
use App\Data\Admin\Refund\RefundOrderData;
use App\Enums\BundlePurchaseStatusEnum;
use App\Enums\EnrollmentRevocationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Events\RefundCompletedEvent;
use App\Exceptions\RefundValidationException;
use App\Jobs\Provisioning\RevokeEnrollmentProviderJob;
use App\Models\BundlePurchase;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\ProvisioningAttempt;
use App\Models\Refund;
use App\Models\Staff;
use App\Models\User;
use App\Services\Payment\Digipay\Data\RefundResponse;
use App\Services\Payment\Digipay\DigipayAdminService;
use App\Services\Provisioning\EnrollmentRevocationService;
use App\Services\Provisioning\ProvisioningAttemptService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

use function Pest\Laravel\assertDatabaseCount;

covers(RefundOrderAction::class);

/**
 * @param  list<array{base: int, paid: int, status?: OrderItemStatusEnum}>  $standalone
 * @param  list<list<array{base: int, paid: int, provider?: string}>>  $bundles
 * @return array{
 *     order: Order,
 *     standalone: list<OrderItem>,
 *     purchases: list<BundlePurchase>,
 *     components: list<list<OrderItem>>,
 *     enrollments: list<list<Enrollment>>
 * }
 */
function mixedOrderScenario(
    array $standalone = [],
    array $bundles = [],
    PaymentMethodEnum $method = PaymentMethodEnum::BANK_TRANSFER,
): array {
    $customer       = User::factory()->create();
    $standalonePaid = array_sum(array_column($standalone, 'paid'));
    $standaloneBase = array_sum(array_column($standalone, 'base'));

    $bundlePaid = 0;
    $bundleBase = 0;
    foreach ($bundles as $components) {
        $bundlePaid += array_sum(array_column($components, 'paid'));
        $bundleBase += array_sum(array_column($components, 'base'));
    }

    $order = Order::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => OrderStatusEnum::COMPLETED,
        'grand_total'            => $standalonePaid + $bundlePaid,
        'full_value_grand_total' => $standaloneBase + $bundleBase,
    ]);
    $order->payments()->create([
        'customer_id' => $customer->id,
        'method'      => $method,
        'amount'      => $standalonePaid + $bundlePaid,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);

    $standaloneItems = [];
    foreach ($standalone as $spec) {
        $option = ProductDeliveryOption::factory()->create(['price' => $spec['base']]);
        $item   = OrderItem::factory()->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $option->id,
            'status'                     => $spec['status'] ?? OrderItemStatusEnum::COMPLETED,
            'price'                      => $spec['base'],
            'total'                      => $spec['paid'],
            'qty_ordered'                => 1,
            'discount_amount'            => 0,
            'tax_amount'                 => 0,
            'pricing_metadata'           => [
                'original_price'        => $spec['base'],
                'base_price_amount'     => $spec['base'],
                'paid_amount'           => $spec['paid'],
                'total_discount_amount' => $spec['base'] - $spec['paid'],
                'discount_amount'       => $spec['base'] - $spec['paid'],
            ],
        ]);
        Enrollment::factory()->create([
            'order_item_id'              => $item->id,
            'order_id'                   => $order->id,
            'customer_id'                => $customer->id,
            'product_delivery_option_id' => $option->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);
        $standaloneItems[] = $item->fresh();
    }

    $purchases     = [];
    $componentSet  = [];
    $enrollmentSet = [];
    foreach ($bundles as $components) {
        $purchase = BundlePurchase::query()->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => ProductDeliveryOption::factory()->create()->id,
            'bundle_name'                => 'Career Bundle',
            'product_name'               => 'Career',
            'name'                       => 'Complete package',
            'sku'                        => 'BUNDLE-MIXED-'.count($purchases),
            'base_value'                 => array_sum(array_column($components, 'base')),
            'selling_price'              => array_sum(array_column($components, 'paid')),
            'composition_version'        => 1,
            'checkout_status'            => OrderStatusEnum::PENDING->value,
            'product_data_snapshot_json' => [],
        ]);

        $items       = [];
        $enrollments = [];
        foreach ($components as $spec) {
            $provider = $spec['provider'] ?? 'moodle';
            $option   = ProductDeliveryOption::factory()->create(['price' => $spec['base']]);
            $item     = OrderItem::factory()->create([
                'order_id'                   => $order->id,
                'bundle_purchase_id'         => $purchase->id,
                'product_delivery_option_id' => $option->id,
                'status'                     => OrderItemStatusEnum::COMPLETED,
                'price'                      => $spec['base'],
                'total'                      => $spec['paid'],
                'qty_ordered'                => 1,
                'discount_amount'            => 0,
                'tax_amount'                 => 0,
                'pricing_metadata'           => [
                    'original_price'        => $spec['base'],
                    'base_price_amount'     => $spec['base'],
                    'paid_amount'           => $spec['paid'],
                    'total_discount_amount' => $spec['base'] - $spec['paid'],
                    'discount_amount'       => $spec['base'] - $spec['paid'],
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
                    'moodle_user_id' => 7, 'moodle_course_id' => 9, 'ims_student_id' => 5, 'ims_enrollment_id' => 6,
                ]]]],
                'provisioning_status' => ProvisioningStatusEnum::HEALTHY,
            ]);

            $items[]       = $item->fresh();
            $enrollments[] = $enrollment->fresh();
        }

        $purchases[]     = $purchase->fresh(['components.enrollment']);
        $componentSet[]  = $items;
        $enrollmentSet[] = $enrollments;
    }

    return [
        'order'       => $order->fresh(),
        'standalone'  => $standaloneItems,
        'purchases'   => $purchases,
        'components'  => $componentSet,
        'enrollments' => $enrollmentSet,
    ];
}

beforeEach(function (): void {
    Event::fake([RefundCompletedEvent::class]);
    Queue::fake([RevokeEnrollmentProviderJob::class]);
});

it('refunds standalone items and indivisible Bundle units exactly once in one operation', function (): void {
    $scenario = mixedOrderScenario(
        standalone: [['base' => 100000, 'paid' => 100000]],
        bundles: [[['base' => 120000, 'paid' => 100000], ['base' => 80000, 'paid' => 50000]]],
    );

    $refunds = app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData());

    expect($refunds)->toHaveCount(3);
    assertDatabaseCount('refunds', 3);

    // Exactly one refund per standalone item and per Bundle component.
    $this->assertDatabaseHas('refunds', [
        'order_item_id' => $scenario['standalone'][0]->id,
        'amount'        => 100000,
        'status'        => RefundStatusEnum::COMPLETED->value,
    ]);
    $this->assertDatabaseHas('refunds', [
        'order_item_id' => $scenario['components'][0][0]->id,
        'amount'        => 100000,
    ]);
    $this->assertDatabaseHas('refunds', [
        'order_item_id' => $scenario['components'][0][1]->id,
        'amount'        => 50000,
    ]);

    $this->assertDatabaseHas('enrollments', [
        'order_item_id'     => $scenario['standalone'][0]->id,
        'enrollment_status' => EnrollmentStatusEnum::CANCELLED->value,
    ]);
    $this->assertDatabaseHas('enrollments', [
        'order_item_id'     => $scenario['components'][0][0]->id,
        'enrollment_status' => EnrollmentStatusEnum::SUSPENDED->value,
        'revocation_status' => EnrollmentRevocationStatusEnum::PENDING->value,
    ]);
    $this->assertDatabaseHas('orders', ['id' => $scenario['order']->id, 'status' => OrderStatusEnum::REFUNDED->value]);
    $this->assertDatabaseHas('orders', ['id' => $scenario['order']->id, 'total_refunded' => 250000]);

    Queue::assertPushed(RevokeEnrollmentProviderJob::class, 2);
    Event::assertDispatched(RefundCompletedEvent::class, 3);
});

it('issues exactly one combined Digipay gateway refund for the whole mixed order', function (): void {
    $scenario = mixedOrderScenario(
        standalone: [['base' => 100000, 'paid' => 100000]],
        bundles: [[['base' => 120000, 'paid' => 100000], ['base' => 80000, 'paid' => 50000]]],
        method: PaymentMethodEnum::DIGIPAY,
    );

    $this->mock(DigipayAdminService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->with(Mockery::type(App\Models\Payment::class), 250000)
            ->andReturn(new RefundResponse(statusCode: 0, message: 'OK', trackingCode: 'DGP-MIXED'));
    });

    app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData());

    expect(Refund::query()->get()->map(
        fn (Refund $refund): ?string => $refund->transaction_details['gateway_tracking_code'] ?? null
    )->all())->toBe(['DGP-MIXED', 'DGP-MIXED', 'DGP-MIXED']);
});

it('distributes a fixed full-order deduction across units by base-value weight', function (): void {
    $scenario = mixedOrderScenario(
        standalone: [['base' => 100000, 'paid' => 100000]],
        bundles: [[['base' => 200000, 'paid' => 150000], ['base' => 100000, 'paid' => 50000]]],
    );

    app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData(deduction_amount: 40000));

    // Units weigh 100k : 300k, so the standalone absorbs 10k and the Bundle 30k.
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $scenario['standalone'][0]->id,
        'amount'           => 90000,
        'deduction_amount' => 10000,
    ]);
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $scenario['components'][0][0]->id,
        'amount'           => 130000,
        'deduction_amount' => 20000,
    ]);
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $scenario['components'][0][1]->id,
        'amount'           => 40000,
        'deduction_amount' => 10000,
    ]);
});

it('applies a percentage deduction per unit from its snapshotted base value', function (): void {
    $scenario = mixedOrderScenario(
        standalone: [['base' => 100000, 'paid' => 100000]],
        bundles: [[['base' => 120000, 'paid' => 100000], ['base' => 80000, 'paid' => 50000]]],
    );

    app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData(deduction_percent: 10));

    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $scenario['standalone'][0]->id,
        'amount'           => 90000,
        'deduction_amount' => 10000,
    ]);
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $scenario['components'][0][0]->id,
        'amount'           => 88000,
        'deduction_amount' => 12000,
    ]);
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $scenario['components'][0][1]->id,
        'amount'           => 42000,
        'deduction_amount' => 8000,
    ]);
});

it('refunds every component of multiple Bundle Purchases without double counting', function (): void {
    $scenario = mixedOrderScenario(
        standalone: [['base' => 50000, 'paid' => 50000]],
        bundles: [
            [['base' => 100000, 'paid' => 100000]],
            [['base' => 100000, 'paid' => 80000], ['base' => 100000, 'paid' => 20000]],
        ],
    );

    $refunds = app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData());

    expect($refunds)->toHaveCount(4);
    assertDatabaseCount('refunds', 4);
    expect($scenario['order']->fresh()->total_refunded)->toBe(250000);
    $this->assertDatabaseHas('orders', ['id' => $scenario['order']->id, 'total_refunded' => 250000]);
});

it('aborts the whole mixed refund before any money moves when one Bundle component is invalid', function (): void {
    $scenario = mixedOrderScenario(
        standalone: [['base' => 100000, 'paid' => 100000]],
        bundles: [[['base' => 100000, 'paid' => 100000], ['base' => 100000, 'paid' => 100000]]],
        method: PaymentMethodEnum::DIGIPAY,
    );
    $scenario['components'][0][1]->update(['status' => OrderItemStatusEnum::REFUNDED]);

    $this->mock(DigipayAdminService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('refund');
    });

    expect(fn () => app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData()))
        ->toThrow(RefundValidationException::class, __('messages.order.refund.already_refunded'));

    assertDatabaseCount('refunds', 0);
    $this->assertDatabaseHas('order_items', [
        'id'     => $scenario['standalone'][0]->id,
        'status' => OrderItemStatusEnum::COMPLETED->value,
    ]);
    Queue::assertNothingPushed();
});

it('refunds the remaining units after a previous partial refund without touching the refunded one', function (): void {
    $scenario = mixedOrderScenario(
        standalone: [
            ['base' => 100000, 'paid' => 100000, 'status' => OrderItemStatusEnum::REFUNDED],
            ['base' => 60000, 'paid' => 60000],
        ],
        bundles: [[['base' => 80000, 'paid' => 80000]]],
    );

    $refunds = app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData());

    expect($refunds)->toHaveCount(2);
    $this->assertDatabaseMissing('refunds', ['order_item_id' => $scenario['standalone'][0]->id]);
    $this->assertDatabaseHas('refunds', ['order_item_id' => $scenario['standalone'][1]->id, 'amount' => 60000]);
    $this->assertDatabaseHas('refunds', ['order_item_id' => $scenario['components'][0][0]->id, 'amount' => 80000]);
});

it('keeps a zero-paid component refund at zero and redistributes inside its Bundle', function (): void {
    $scenario = mixedOrderScenario(
        standalone: [['base' => 100000, 'paid' => 100000]],
        bundles: [[['base' => 200000, 'paid' => 100000], ['base' => 100000, 'paid' => 0]]],
    );

    app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData(deduction_percent: 10));

    // Bundle target = 30,000; the 10,000 on the free component spills onto the paid one.
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $scenario['components'][0][0]->id,
        'amount'           => 70000,
        'deduction_amount' => 30000,
    ]);
    $this->assertDatabaseHas('refunds', [
        'order_item_id'    => $scenario['components'][0][1]->id,
        'amount'           => 0,
        'deduction_amount' => 0,
    ]);
});

it('records the full-order audit on every refund line', function (): void {
    $scenario = mixedOrderScenario(
        standalone: [['base' => 100000, 'paid' => 100000]],
        bundles: [[['base' => 100000, 'paid' => 100000]]],
    );

    app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData());

    $standaloneRefund = Refund::query()->where('order_item_id', $scenario['standalone'][0]->id)->sole();
    expect($standaloneRefund->transaction_details['full_order_refund'])->toMatchArray([
        'commercial_unit' => 'standalone',
        'base_value'      => 100000,
        'paid_value'      => 100000,
        'refund_amount'   => 100000,
    ]);

    $bundleRefund = Refund::query()->where('order_item_id', $scenario['components'][0][0]->id)->sole();
    expect($bundleRefund->transaction_details['full_order_refund'])->toMatchArray([
        'commercial_unit' => 'bundle',
        'base_value'      => 100000,
        'paid_value'      => 100000,
    ])->and($bundleRefund->transaction_details['bundle_refund'])->toMatchArray([
        'bundle_purchase_id' => $scenario['purchases'][0]->id,
    ]);
});

it('rejects a partial Digipay refund when a unit is already refunded and partial refunds are disabled', function (): void {
    config()->set('payments.digipay.allow_partial_refund', false);
    $scenario = mixedOrderScenario(
        standalone: [
            ['base' => 100000, 'paid' => 100000, 'status' => OrderItemStatusEnum::REFUNDED],
            ['base' => 60000, 'paid' => 60000],
        ],
        method: PaymentMethodEnum::DIGIPAY,
    );

    $this->mock(DigipayAdminService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('refund');
    });

    expect(fn () => app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData()))
        ->toThrow(RefundValidationException::class, __('messages.order.refund.digipay_partial_refund_not_supported'));

    assertDatabaseCount('refunds', 0);
});

it('keeps the single gateway amount equal to the sum of the refund lines', function (): void {
    $scenario = mixedOrderScenario(
        standalone: [['base' => 100000, 'paid' => 90000]],
        bundles: [[['base' => 120000, 'paid' => 100000], ['base' => 80000, 'paid' => 50000]]],
        method: PaymentMethodEnum::DIGIPAY,
    );

    $this->mock(DigipayAdminService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refund')->once()->with(Mockery::type(App\Models\Payment::class), 240000)
            ->andReturn(new RefundResponse(statusCode: 0, message: 'OK', trackingCode: 'DGP-SUM'));
    });

    $refunds = app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData());

    expect($refunds->sum('amount'))->toBe(240000);
});

it('preserves successful revocations when another component cannot be revoked and retries without a new gateway refund', function (): void {
    $scenario = mixedOrderScenario(
        bundles: [[
            ['base' => 100000, 'paid' => 100000, 'provider' => 'moodle'],
            ['base' => 100000, 'paid' => 100000, 'provider' => 'ims'],
        ]],
        method: PaymentMethodEnum::DIGIPAY,
    );
    $staff = Staff::factory()->create();

    $this->mock(DigipayAdminService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->andReturn(new RefundResponse(statusCode: 0, message: 'OK', trackingCode: 'DGP-REVOKE'));
    });

    app(RefundOrderAction::class)->handle($scenario['order'], new RefundOrderData());

    // Moodle queues a revocation attempt; IMS has no revocation API and is manual.
    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $scenario['enrollments'][0][0]->id,
        'provider'      => ProvisioningProviderEnum::MOODLE->value,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION->value,
        'status'        => ProvisioningAttemptStatusEnum::QUEUED->value,
    ]);
    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $scenario['enrollments'][0][1]->id,
        'provider'      => ProvisioningProviderEnum::IMS->value,
        'status'        => ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED->value,
    ]);
    expect($scenario['purchases'][0]->fresh()->status)->toBe(BundlePurchaseStatusEnum::REVOCATION_PENDING);

    $revocations = app(EnrollmentRevocationService::class);

    // Moodle revocation succeeds and stays revoked while IMS remains manual.
    $moodleAttempt = ProvisioningAttempt::query()
        ->where('enrollment_id', $scenario['enrollments'][0][0]->id)
        ->where('trigger', ProvisioningTriggerEnum::REVOCATION->value)
        ->sole();
    $running = app(ProvisioningAttemptService::class)->start($moodleAttempt->id);
    $revocations->succeed($running ?? throw new RuntimeException('Attempt did not start.'),
        ['moodle_user_id' => 7, 'moodle_course_id' => 9]);

    expect($scenario['purchases'][0]->fresh()->status)->toBe(BundlePurchaseStatusEnum::REVOCATION_PENDING)
        ->and($scenario['enrollments'][0][0]->fresh()->revocation_status)->toBe(EnrollmentRevocationStatusEnum::REVOKED);

    // Retrying only the outstanding revocation never repeats the gateway refund.
    $revocations->retry($scenario['enrollments'][0][1]);
    assertDatabaseCount('refunds', 2);

    // Manually confirming the unsupported IMS revocation completes the Bundle.
    $revocations->confirmManually($scenario['enrollments'][0][1], $staff->id);
    expect($scenario['purchases'][0]->fresh()->status)->toBe(BundlePurchaseStatusEnum::REFUNDED);
});

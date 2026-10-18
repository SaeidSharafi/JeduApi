<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Dashboard\DashboardSummaryReport;

use function Pest\Laravel\getJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

mutates(DashboardSummaryReport::class);

const SUMMARY_ENDPOINT = '/api/v1/admin/dashboard/summary';

/**
 * Every test pins "now" to 2025-08-27 12:00 Tehran (1404-06-05):
 * 30d window = 2025-07-29..2025-08-27 (1404-05-07..1404-06-05),
 * previous   = 2025-06-29..2025-07-28 (1404-04-08..1404-05-06).
 */
function summaryOrder(int $grandTotal): Order
{
    return Order::factory()->create([
        'status'      => OrderStatusEnum::COMPLETED,
        'grand_total' => $grandTotal,
    ]);
}

function summaryPayment(
    Order $order,
    int $amount,
    string $createdAt,
    PaymentStatusEnum $status = PaymentStatusEnum::COMPLETED,
    PaymentMethodEnum $method = PaymentMethodEnum::MELLAT_GATEWAY,
): Payment {
    return Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $order->customer_id,
        'amount'      => $amount,
        'status'      => $status,
        'purpose'     => PaymentPurposeEnum::ORDER,
        'method'      => $method,
        'created_by'  => null,
        'created_at'  => $createdAt,
        'updated_at'  => $createdAt,
    ]);
}

function summaryRefund(
    Order $order,
    int $amount,
    int $deductionAmount,
    string $completedAt,
    ?string $createdAt = null,
): Refund {
    return Refund::factory()->create([
        'order_id'         => $order->id,
        'amount'           => $amount,
        'deduction_amount' => $deductionAmount,
        'status'           => RefundStatusEnum::COMPLETED,
        'created_by'       => null,
        'refunded_at'      => $completedAt,
        'created_at'       => $createdAt ?? $completedAt,
        'updated_at'       => $completedAt,
    ]);
}

function summaryTopUp(
    int $amount,
    string $createdAt,
    PaymentStatusEnum $status = PaymentStatusEnum::COMPLETED,
): Payment {
    return Payment::factory()->topup()->create([
        'amount'     => $amount,
        'status'     => $status,
        'created_by' => null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

function summaryEnrollment(User $customer, string $createdAt): Enrollment
{
    return Enrollment::factory()->create([
        'customer_id' => $customer->id,
        'created_at'  => $createdAt,
        'updated_at'  => $createdAt,
    ]);
}

it('rejects an unauthenticated request', function (): void {
    getJson(SUMMARY_ENDPOINT)->assertUnauthorized();
});

it('forbids a staff member holding only the dashboard view permission', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    getJson(SUMMARY_ENDPOINT)->assertForbidden();
});

it('returns the resolved period, five kpis and the daily trend envelope', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'period' => ['key', 'start', 'end', 'previous_start', 'previous_end'],
            'kpis'   => [
                'net_revenue'         => ['value', 'previous', 'change_pct'],
                'paid_orders'         => ['value', 'previous', 'change_pct'],
                'aov'                 => ['value', 'previous', 'change_pct'],
                'new_learners'        => ['value', 'previous', 'change_pct'],
                'unfinished_payments' => ['value', 'previous', 'change_pct', 'amount', 'previous_amount'],
            ],
            'trend' => [
                'start',
                'end',
                'previous_start',
                'previous_end',
                'points'          => [['date', 'revenue', 'orders']],
                'previous_points' => [['date', 'revenue', 'orders']],
            ],
        ],
        'metadata' => ['generated_at', 'cached', 'cache_ttl', 'timezone', 'currency'],
    ]);

    expect(array_keys($response->json('data')))->toBe(['period', 'kpis', 'trend'])
        ->and(array_keys($response->json('data.kpis')))->toBe([
            'net_revenue', 'paid_orders', 'aov', 'new_learners', 'unfinished_payments',
        ])
        ->and(array_keys($response->json('data.kpis.net_revenue')))->toBe(['value', 'previous', 'change_pct'])
        ->and(array_keys($response->json('data.kpis.unfinished_payments')))
        ->toBe(['value', 'previous', 'change_pct', 'amount', 'previous_amount']);

    $response->assertJsonPath('data.period.key', '30d');
    $response->assertJsonPath('data.period.start', '1404-05-07');
    $response->assertJsonPath('data.period.end', '1404-06-05');
    $response->assertJsonPath('data.period.previous_start', '1404-04-08');
    $response->assertJsonPath('data.period.previous_end', '1404-05-06');
    $response->assertJsonPath('data.trend.start', '1404-05-07');
    $response->assertJsonPath('data.trend.end', '1404-06-05');
    $response->assertJsonPath('data.trend.previous_start', '1404-04-08');
    $response->assertJsonPath('data.trend.previous_end', '1404-05-06');
    $response->assertJsonPath('metadata.cached', false);
    $response->assertJsonPath('metadata.cache_ttl', 60);
    $response->assertJsonPath('metadata.timezone', 'Asia/Tehran');
    $response->assertJsonPath('metadata.currency', 'IRR');
});

it('calculates net revenue from completed order payments minus completed refunds', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $order = summaryOrder(1_000);
    summaryPayment($order, 1_000, '2025-08-10 10:00:00');
    summaryPayment($order, 500, '2025-08-12 10:00:00', PaymentStatusEnum::FAILED);
    summaryPayment($order, 400, '2025-08-13 10:00:00', PaymentStatusEnum::PENDING);
    summaryRefund($order, 300, 50, '2025-08-15 10:00:00');

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.kpis.net_revenue.value', 700);
});

it('dates refunds by their completion timestamp rather than the request timestamp', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $order = summaryOrder(1_000);
    summaryPayment($order, 1_000, '2025-08-10 10:00:00');
    summaryRefund($order, 200, 0, '2025-08-15 10:00:00', createdAt: '2025-05-01 10:00:00');
    summaryRefund($order, 100, 0, '2025-09-05 10:00:00', createdAt: '2025-08-20 10:00:00');

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.kpis.net_revenue.value', 800);
});

it('counts wallet-funded order payments but never wallet top-ups', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $order = summaryOrder(1_000);
    summaryPayment($order, 1_000, '2025-08-10 10:00:00', method: PaymentMethodEnum::WALLET);
    summaryTopUp(5_000, '2025-08-11 10:00:00');

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.kpis.net_revenue.value', 1_000);
    $response->assertJsonPath('data.kpis.paid_orders.value', 1);
    $response->assertJsonPath('data.kpis.aov.value', 1_000);
});

it('excludes free orders from paid orders and average order value', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $paidOrder = summaryOrder(1_000);
    summaryPayment($paidOrder, 1_000, '2025-08-10 10:00:00');
    $freeOrder = summaryOrder(0);
    summaryPayment($freeOrder, 0, '2025-08-11 10:00:00', method: PaymentMethodEnum::NO_PAYMENT);

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.kpis.paid_orders.value', 1);
    $response->assertJsonPath('data.kpis.net_revenue.value', 1_000);
    $response->assertJsonPath('data.kpis.aov.value', 1_000);
});

it('divides net revenue by paid orders for the average order value', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $firstOrder = summaryOrder(500);
    summaryPayment($firstOrder, 500, '2025-08-10 10:00:00');
    $secondOrder = summaryOrder(500);
    summaryPayment($secondOrder, 500, '2025-08-11 10:00:00');
    summaryRefund($firstOrder, 200, 0, '2025-08-12 10:00:00');

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.kpis.net_revenue.value', 800);
    $response->assertJsonPath('data.kpis.paid_orders.value', 2);
    $response->assertJsonPath('data.kpis.aov.value', 400);
});

it('compares every kpi with the previous window using a one-decimal change', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $currentOrder = summaryOrder(1_000);
    summaryPayment($currentOrder, 1_000, '2025-08-10 10:00:00');
    $previousOrder = summaryOrder(300);
    summaryPayment($previousOrder, 300, '2025-07-10 10:00:00');

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.kpis.net_revenue.value', 1_000);
    $response->assertJsonPath('data.kpis.net_revenue.previous', 300);
    $response->assertJsonPath('data.kpis.net_revenue.change_pct', 233.3);
    $response->assertJsonPath('data.kpis.paid_orders.value', 1);
    $response->assertJsonPath('data.kpis.paid_orders.previous', 1);
    $response->assertJsonPath('data.kpis.paid_orders.change_pct', 0.0);
});

it('reports a null change when the previous window is zero', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $order = summaryOrder(1_000);
    summaryPayment($order, 1_000, '2025-08-10 10:00:00');

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.kpis.net_revenue.value', 1_000);
    $response->assertJsonPath('data.kpis.net_revenue.previous', 0);
    $response->assertJsonPath('data.kpis.net_revenue.change_pct', null);
    $response->assertJsonPath('data.kpis.paid_orders.change_pct', null);
    $response->assertJsonPath('data.kpis.aov.change_pct', null);
    $response->assertJsonPath('data.kpis.new_learners.change_pct', null);
    $response->assertJsonPath('data.kpis.unfinished_payments.change_pct', null);
});

it('counts new learners as customers whose first enrollment falls in the window', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $newLearner = User::factory()->create();
    summaryEnrollment($newLearner, '2025-08-10 10:00:00');

    $returningLearner = User::factory()->create();
    summaryEnrollment($returningLearner, '2025-06-01 10:00:00');
    summaryEnrollment($returningLearner, '2025-08-20 10:00:00');

    $previousLearner = User::factory()->create();
    summaryEnrollment($previousLearner, '2025-07-10 10:00:00');

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.kpis.new_learners.value', 1);
    $response->assertJsonPath('data.kpis.new_learners.previous', 1);
});

it('counts unfinished payments and their amount for both windows', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $order = summaryOrder(1_000);
    summaryPayment($order, 100, '2025-08-10 10:00:00', PaymentStatusEnum::PENDING);
    summaryPayment($order, 200, '2025-08-11 10:00:00', PaymentStatusEnum::PENDING);
    summaryPayment($order, 500, '2025-08-12 10:00:00');
    summaryPayment($order, 50, '2025-07-10 10:00:00', PaymentStatusEnum::PENDING);
    summaryPayment($order, 70, '2025-06-20 10:00:00', PaymentStatusEnum::PENDING);

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.kpis.unfinished_payments.value', 2);
    $response->assertJsonPath('data.kpis.unfinished_payments.amount', 300);
    $response->assertJsonPath('data.kpis.unfinished_payments.previous', 1);
    $response->assertJsonPath('data.kpis.unfinished_payments.previous_amount', 50);
    $response->assertJsonPath('data.kpis.unfinished_payments.change_pct', 100.0);
});

it('counts a pending wallet top-up as an unfinished payment', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    summaryTopUp(5_000, '2025-08-11 10:00:00', PaymentStatusEnum::PENDING);

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.kpis.unfinished_payments.value', 1);
    $response->assertJsonPath('data.kpis.unfinished_payments.amount', 5_000);
});

it('zero-fills the daily trend and the previous trend for the whole period', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $paidOrder = summaryOrder(1_000);
    summaryPayment($paidOrder, 1_000, '2025-08-10 10:00:00');
    $refundedOrder = summaryOrder(500);
    summaryPayment($refundedOrder, 500, '2025-08-12 10:00:00');
    summaryRefund($refundedOrder, 200, 0, '2025-08-12 10:00:00');
    $freeOrder = summaryOrder(0);
    summaryPayment($freeOrder, 0, '2025-08-11 10:00:00', method: PaymentMethodEnum::NO_PAYMENT);

    $response = getJson(SUMMARY_ENDPOINT);

    $response->assertOk();

    $points         = $response->json('data.trend.points');
    $previousPoints = $response->json('data.trend.previous_points');

    expect($points)->toHaveCount(30)
        ->and(array_sum(array_column($points, 'revenue')))->toBe(1_300)
        ->and(array_sum(array_column($points, 'orders')))->toBe(2)
        ->and($points[0])->toBe(['date' => '1404-05-07', 'revenue' => 0, 'orders' => 0])
        ->and($points[12])->toBe(['date' => '1404-05-19', 'revenue' => 1_000, 'orders' => 1])
        ->and($points[13])->toBe(['date' => '1404-05-20', 'revenue' => 0, 'orders' => 0])
        ->and($points[14])->toBe(['date' => '1404-05-21', 'revenue' => 300, 'orders' => 1])
        ->and($points[29])->toBe(['date' => '1404-06-05', 'revenue' => 0, 'orders' => 0])
        ->and($previousPoints)->toHaveCount(30)
        ->and($previousPoints[0])->toBe(['date' => '1404-04-08', 'revenue' => 0, 'orders' => 0])
        ->and($previousPoints[29])->toBe(['date' => '1404-05-06', 'revenue' => 0, 'orders' => 0])
        ->and(array_sum(array_column($previousPoints, 'revenue')))->toBe(0);
});

it('returns a seven-day trend for today while comparing the kpis with yesterday', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $order = summaryOrder(400);
    summaryPayment($order, 400, '2025-08-26 10:00:00');

    $response = getJson(SUMMARY_ENDPOINT.'?period=today');

    $response->assertOk();
    $response->assertJsonPath('data.period.key', 'today');
    $response->assertJsonPath('data.period.start', '1404-06-05');
    $response->assertJsonPath('data.period.end', '1404-06-05');
    $response->assertJsonPath('data.period.previous_start', '1404-06-04');
    $response->assertJsonPath('data.period.previous_end', '1404-06-04');
    $response->assertJsonPath('data.trend.start', '1404-05-30');
    $response->assertJsonPath('data.trend.end', '1404-06-05');
    $response->assertJsonPath('data.trend.previous_start', '1404-05-23');
    $response->assertJsonPath('data.trend.previous_end', '1404-05-29');
    $response->assertJsonPath('data.kpis.net_revenue.value', 0);
    $response->assertJsonPath('data.kpis.net_revenue.previous', 400);
    $response->assertJsonPath('data.kpis.paid_orders.value', 0);
    $response->assertJsonPath('data.kpis.paid_orders.previous', 1);

    $points = $response->json('data.trend.points');

    expect($points)->toHaveCount(7)
        ->and($response->json('data.trend.previous_points'))->toHaveCount(7)
        ->and($points[0])->toBe(['date' => '1404-05-30', 'revenue' => 0, 'orders' => 0])
        ->and($points[5])->toBe(['date' => '1404-06-04', 'revenue' => 400, 'orders' => 1])
        ->and($points[6])->toBe(['date' => '1404-06-05', 'revenue' => 0, 'orders' => 0]);
});

it('rejects an invalid period', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    getJson(SUMMARY_ENDPOINT.'?period=90d')->assertUnprocessable();
});

it('serves the cached block until fresh=1 recomputes it', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-08-27 12:00:00');

    $order = summaryOrder(1_000);
    summaryPayment($order, 1_000, '2025-08-10 10:00:00');

    $first = getJson(SUMMARY_ENDPOINT);
    $first->assertOk();
    $first->assertJsonPath('data.kpis.net_revenue.value', 1_000);
    $first->assertJsonPath('metadata.cached', false);
    $first->assertJsonPath('metadata.generated_at', '1404-06-05 12:00:00');

    summaryPayment($order, 500, '2025-08-11 10:00:00');
    $this->travel(30)->seconds();

    $cached = getJson(SUMMARY_ENDPOINT);
    $cached->assertOk();
    $cached->assertJsonPath('data.kpis.net_revenue.value', 1_000);
    $cached->assertJsonPath('metadata.cached', true);
    $cached->assertJsonPath('metadata.generated_at', '1404-06-05 12:00:00');

    $fresh = getJson(SUMMARY_ENDPOINT.'?fresh=1');
    $fresh->assertOk();
    $fresh->assertJsonPath('data.kpis.net_revenue.value', 1_500);
    $fresh->assertJsonPath('metadata.cached', false);
    $fresh->assertJsonPath('metadata.generated_at', '1404-06-05 12:00:30');
});

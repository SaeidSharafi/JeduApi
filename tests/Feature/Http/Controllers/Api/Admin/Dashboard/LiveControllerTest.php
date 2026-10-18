<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentTransactionErrorCodeEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Models\Enrollment;
use App\Models\ImportRun;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\ProvisioningAttempt;
use App\Models\Refund;
use App\Models\SmsLog;
use App\Services\Dashboard\DashboardLiveReport;
use Illuminate\Support\Carbon;

use function Pest\Laravel\getJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

mutates(DashboardLiveReport::class);

const LIVE_ENDPOINT = '/api/v1/admin/dashboard/live';

/**
 * Create a payment transaction and backdate when it was recorded.
 */
function liveGatewayTransaction(
    PaymentTransactionStatusEnum $status,
    ?string $errorCode = null,
    ?Carbon $createdAt = null,
): PaymentTransaction {
    $transaction = PaymentTransaction::factory()->create([
        'status'     => $status,
        'error_code' => $errorCode,
    ]);

    if ($createdAt instanceof Carbon) {
        $transaction->forceFill(['created_at' => $createdAt])->save();
    }

    return $transaction;
}

/**
 * Create an SMS log row: `0` is a skipped send, anything else is an HTTP status.
 */
function liveSmsLog(int $status, ?Carbon $sentAt = null): SmsLog
{
    return SmsLog::create([
        'status'  => $status,
        'type'    => 'verification',
        'to'      => ['09120000000'],
        'from'    => '1000',
        'content' => 'verification code',
        'sent_at' => $sentAt ?? Carbon::now(),
    ]);
}

/**
 * Create a refund recorded at the given time.
 */
function liveRefund(RefundStatusEnum $status, int $amount, Carbon $createdAt): Refund
{
    return Refund::factory()->create([
        'status'     => $status,
        'amount'     => $amount,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

/**
 * Create an order whose last update is the given moment.
 */
function liveOrder(OrderStatusEnum $status, Carbon $updatedAt): Order
{
    $order             = Order::factory()->create(['status' => $status]);
    $order->timestamps = false;
    $order->forceFill(['updated_at' => $updatedAt])->save();

    return $order;
}

/**
 * Create an active or inactive enrollment with the given provisioning state.
 */
function liveEnrollment(
    EnrollmentStatusEnum $status = EnrollmentStatusEnum::ACTIVE,
    ProvisioningStatusEnum $provisioningStatus = ProvisioningStatusEnum::HEALTHY,
): Enrollment {
    $enrollment = Enrollment::factory()->create(['enrollment_status' => $status]);

    // The creating observer derives provisioning_status from the product plan,
    // so the state under test is applied after the row exists.
    $enrollment->forceFill(['provisioning_status' => $provisioningStatus])->saveQuietly();

    return $enrollment;
}

function liveProvisioningAttempt(
    Enrollment $enrollment,
    ProvisioningProviderEnum $provider,
    int $sequence,
    ProvisioningAttemptStatusEnum $status,
): ProvisioningAttempt {
    return ProvisioningAttempt::create([
        'enrollment_id' => $enrollment->id,
        'provider'      => $provider,
        'trigger'       => ProvisioningTriggerEnum::PAYMENT,
        'status'        => $status,
        'sequence'      => $sequence,
    ]);
}

it('rejects an unauthenticated request', function (): void {
    getJson(LIVE_ENDPOINT)->assertUnauthorized();
});

it('forbids a staff member without the dashboard view permission', function (): void {
    $this->authorized_user([]);

    getJson(LIVE_ENDPOINT)->assertForbidden();
});

it('serves the live block to a staff member holding only dashboard.view', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    $response = getJson(LIVE_ENDPOINT);

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'ops' => [
                'gateway_errors'        => ['count', 'rate_pct'],
                'provisioning_failures' => ['count'],
                'pending_refunds'       => ['count', 'amount', 'oldest_created_at'],
                'stuck_processing'      => ['count', 'oldest_updated_at'],
            ],
            'health' => [
                'gateway'      => ['rate_pct', 'sample'],
                'provisioning' => ['healthy_count', 'active_count', 'healthy_share_pct', 'open_failures'],
                'sms'          => ['rate_pct', 'sample', 'skipped'],
                'imports'      => ['running'],
            ],
        ],
        'metadata' => [
            'generated_at',
            'cached',
            'cache_ttl',
            'timezone',
            'currency',
        ],
    ]);
    $response->assertJsonPath('metadata.cached', false);
    $response->assertJsonPath('metadata.cache_ttl', 30);
    $response->assertJsonPath('metadata.timezone', 'Asia/Tehran');
    $response->assertJsonPath('metadata.currency', 'IRR');
});

it('returns exactly the four operations keys and never a tickets key', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    $response = getJson(LIVE_ENDPOINT);

    $response->assertOk();
    expect(array_keys($response->json('data.ops')))
        ->toBe(['gateway_errors', 'provisioning_failures', 'pending_refunds', 'stuck_processing'])
        ->and($response->json('data.ops'))->not->toHaveKey('tickets');
});

it('excludes lifecycle error codes from the last-24h gateway errors and rate', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);
    $now = Carbon::now();

    liveGatewayTransaction(PaymentTransactionStatusEnum::COMPLETED, null, $now);
    liveGatewayTransaction(PaymentTransactionStatusEnum::FAILED, null, $now);
    liveGatewayTransaction(PaymentTransactionStatusEnum::FAILED, 'GATEWAY_ERROR', $now);
    liveGatewayTransaction(PaymentTransactionStatusEnum::FAILED, PaymentTransactionErrorCodeEnum::ORDER_CLOSED->value, $now);
    liveGatewayTransaction(PaymentTransactionStatusEnum::FAILED, PaymentTransactionErrorCodeEnum::STUCK_TIMEOUT->value, $now);
    liveGatewayTransaction(PaymentTransactionStatusEnum::FAILED, null, $now->copy()->subHours(25));

    $response = getJson(LIVE_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.ops.gateway_errors.count', 2);
    $response->assertJsonPath('data.ops.gateway_errors.rate_pct', 33.3);
    $response->assertJsonPath('data.health.gateway.rate_pct', 33.3);
    $response->assertJsonPath('data.health.gateway.sample', 3);
});

it('counts provisioning failures from the latest attempt per enrollment and provider', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    $recovered = liveEnrollment();
    liveProvisioningAttempt($recovered, ProvisioningProviderEnum::MOODLE, 1, ProvisioningAttemptStatusEnum::FAILED);
    liveProvisioningAttempt($recovered, ProvisioningProviderEnum::MOODLE, 2, ProvisioningAttemptStatusEnum::SUCCEEDED);

    $broken = liveEnrollment();
    liveProvisioningAttempt($broken, ProvisioningProviderEnum::MOODLE, 1, ProvisioningAttemptStatusEnum::SUCCEEDED);
    liveProvisioningAttempt($broken, ProvisioningProviderEnum::MOODLE, 2, ProvisioningAttemptStatusEnum::FAILED);

    $manual = liveEnrollment();
    liveProvisioningAttempt($manual, ProvisioningProviderEnum::MOODLE, 1, ProvisioningAttemptStatusEnum::FAILED);
    liveProvisioningAttempt($manual, ProvisioningProviderEnum::MOODLE, 2, ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED);

    $bothProviders = liveEnrollment();
    liveProvisioningAttempt($bothProviders, ProvisioningProviderEnum::MOODLE, 1, ProvisioningAttemptStatusEnum::FAILED);
    liveProvisioningAttempt($bothProviders, ProvisioningProviderEnum::SPOTPLAYER, 1, ProvisioningAttemptStatusEnum::FAILED);

    $response = getJson(LIVE_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.ops.provisioning_failures.count', 4);
    $response->assertJsonPath('data.health.provisioning.open_failures', 4);
});

it('reports pending refunds with their amount and the oldest creation time in Jalali', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);
    $this->travelTo('2025-01-01 12:00:00');

    $oldest = Carbon::now()->subHours(5);
    liveRefund(RefundStatusEnum::PENDING, 300, Carbon::now()->subHours(3));
    liveRefund(RefundStatusEnum::PROCESSING, 200, $oldest);
    liveRefund(RefundStatusEnum::COMPLETED, 999, Carbon::now()->subHours(1));

    $response = getJson(LIVE_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.ops.pending_refunds.count', 2);
    $response->assertJsonPath('data.ops.pending_refunds.amount', 500);
    $response->assertJsonPath('data.ops.pending_refunds.oldest_created_at', $this->toJalalitString($oldest));
    expect($response->json('data.ops.pending_refunds.oldest_created_at'))
        ->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});

it('reports processing orders idle for more than 48 hours only', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);
    $this->travelTo('2025-01-01 12:00:00');

    $oldest = Carbon::now()->subHours(49);
    liveOrder(OrderStatusEnum::PROCESSING, $oldest);
    liveOrder(OrderStatusEnum::PROCESSING, Carbon::now()->subHours(47));
    liveOrder(OrderStatusEnum::COMPLETED, Carbon::now()->subHours(49));

    $response = getJson(LIVE_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.ops.stuck_processing.count', 1);
    $response->assertJsonPath('data.ops.stuck_processing.oldest_updated_at', $this->toJalalitString($oldest));
});

it('splits skipped SMS sends from failed sends in the last 24 hours', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);
    $now = Carbon::now();

    liveSmsLog(200, $now->copy()->subHour());
    liveSmsLog(201, $now->copy()->subHours(2));
    liveSmsLog(500, $now->copy()->subHours(3));
    liveSmsLog(SmsLog::STATUS_SKIPPED, $now->copy()->subHours(4));
    liveSmsLog(200, $now->copy()->subHours(25));

    $response = getJson(LIVE_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.health.sms.rate_pct', 66.7);
    $response->assertJsonPath('data.health.sms.sample', 3);
    $response->assertJsonPath('data.health.sms.skipped', 1);
});

it('reports a null rate when the sample is empty', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    $response = getJson(LIVE_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.ops.gateway_errors.count', 0);
    $response->assertJsonPath('data.ops.gateway_errors.rate_pct', null);
    $response->assertJsonPath('data.health.gateway.rate_pct', null);
    $response->assertJsonPath('data.health.gateway.sample', 0);
    $response->assertJsonPath('data.health.sms.rate_pct', null);
    $response->assertJsonPath('data.health.sms.sample', 0);
    $response->assertJsonPath('data.health.sms.skipped', 0);
    $response->assertJsonPath('data.ops.pending_refunds.oldest_created_at', null);
    $response->assertJsonPath('data.ops.stuck_processing.oldest_updated_at', null);
});

it('reports the healthy share of active enrollments', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    liveEnrollment(EnrollmentStatusEnum::ACTIVE, ProvisioningStatusEnum::HEALTHY);
    liveEnrollment(EnrollmentStatusEnum::ACTIVE, ProvisioningStatusEnum::HEALTHY);
    liveEnrollment(EnrollmentStatusEnum::ACTIVE, ProvisioningStatusEnum::DEGRADED);
    liveEnrollment(EnrollmentStatusEnum::EXPIRED, ProvisioningStatusEnum::HEALTHY);
    liveEnrollment(EnrollmentStatusEnum::SUSPENDED, ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED);

    $response = getJson(LIVE_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.health.provisioning.active_count', 3);
    $response->assertJsonPath('data.health.provisioning.healthy_count', 2);
    $response->assertJsonPath('data.health.provisioning.healthy_share_pct', 66.7);
});

it('reports the number of running imports', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    ImportRun::factory()->create(['status' => ImportRunStatusEnum::PROCESSING]);
    ImportRun::factory()->create(['status' => ImportRunStatusEnum::PROCESSING]);
    ImportRun::factory()->create(['status' => ImportRunStatusEnum::COMPLETED]);

    $response = getJson(LIVE_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.health.imports.running', 2);
});

it('serves the cached snapshot until fresh=1 recomputes it', function (): void {
    $this->travelTo('2025-01-01 12:00:00');
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);
    liveGatewayTransaction(PaymentTransactionStatusEnum::FAILED, null, Carbon::now());

    $first = getJson(LIVE_ENDPOINT);
    $first->assertOk();
    $first->assertJsonPath('data.ops.gateway_errors.count', 1);
    $first->assertJsonPath('metadata.cached', false);
    $first->assertJsonPath('metadata.generated_at', '1403-10-12 12:00:00');

    liveGatewayTransaction(PaymentTransactionStatusEnum::FAILED, null, Carbon::now());
    $this->travel(5)->seconds();

    $cached = getJson(LIVE_ENDPOINT);
    $cached->assertOk();
    $cached->assertJsonPath('data.ops.gateway_errors.count', 1);
    $cached->assertJsonPath('metadata.cached', true);
    $cached->assertJsonPath('metadata.generated_at', '1403-10-12 12:00:00');

    $fresh = getJson(LIVE_ENDPOINT.'?fresh=1');
    $fresh->assertOk();
    $fresh->assertJsonPath('data.ops.gateway_errors.count', 2);
    $fresh->assertJsonPath('metadata.cached', false);
    $fresh->assertJsonPath('metadata.generated_at', '1403-10-12 12:00:05');
});

it('ignores a period parameter instead of rejecting it', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    getJson(LIVE_ENDPOINT.'?period=last-century')->assertOk();
});

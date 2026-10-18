<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Dashboard\DashboardLiveData;
use App\Data\Admin\Dashboard\DashboardRefreshQueryData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentTransactionErrorCodeEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\ImportRun;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\ProvisioningAttempt;
use App\Models\Refund;
use App\Models\SmsLog;
use App\Services\Dashboard\DashboardBlockCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Dashboard
 *
 * @authenticated
 */
final class LiveController extends Controller
{
    private const int GATEWAY_WINDOW_HOURS = 24;

    private const int SMS_WINDOW_HOURS = 24;

    private const int STUCK_PROCESSING_HOURS = 48;

    /**
     * Get the live operations queue and system health.
     *
     * A snapshot of now and the last 24 hours: real gateway failures with the
     * gateway success rate, provisioning failures and the healthy share of
     * active enrollments, pending refunds, orders stuck in processing, the SMS
     * success rate with skipped sends reported separately, and running imports.
     * This block ignores `period` and never returns financial data.
     *
     * @responseFile 200 resources/responses/admin/dashboard/live.json
     */
    public function __invoke(DashboardRefreshQueryData $query, DashboardBlockCache $cache): ApiResponseInterface
    {
        Gate::authorize(PermissionEnum::DASHBOARD_VIEW->value);

        $block = $cache->remember(
            CacheKey::AdminDashboardLive,
            [],
            fn (): array => $this->snapshot(),
            $query->fresh,
        );

        return apiResponse()->success(DashboardLiveData::from($block['payload']), metadata: $block['metadata']);
    }

    /**
     * Compose the operations queue and the health signals.
     *
     * `open_failures` reuses the provisioning-failure count so the two blocks
     * can never disagree, and the gateway rate is computed once for both.
     *
     * @return array{
     *     ops: array{
     *         gateway_errors: array{count: int, rate_pct: float|null},
     *         provisioning_failures: array{count: int},
     *         pending_refunds: array{count: int, amount: int, oldest_created_at: string|null},
     *         stuck_processing: array{count: int, oldest_updated_at: string|null},
     *     },
     *     health: array{
     *         gateway: array{rate_pct: float|null, sample: int},
     *         provisioning: array{
     *             healthy_count: int,
     *             active_count: int,
     *             healthy_share_pct: float|null,
     *             open_failures: int,
     *         },
     *         sms: array{rate_pct: float|null, sample: int, skipped: int},
     *         imports: array{running: int},
     *     },
     * }
     */
    private function snapshot(): array
    {
        $gateway              = $this->gatewayStats();
        $provisioningFailures = $this->provisioningFailuresCount();
        $provisioningHealth   = $this->provisioningHealth();
        $pendingRefunds       = $this->pendingRefunds();
        $stuckProcessing      = $this->stuckProcessing();
        $sms                  = $this->smsStats();

        $activeCount         = $provisioningHealth['active_count'];
        $healthySharePercent = $activeCount > 0
            ? round($provisioningHealth['healthy_count'] / $activeCount * 100, 1)
            : null;

        return [
            'ops' => [
                'gateway_errors' => [
                    'count'    => $gateway['count'],
                    'rate_pct' => $gateway['rate_pct'],
                ],
                'provisioning_failures' => [
                    'count' => $provisioningFailures,
                ],
                'pending_refunds'  => $pendingRefunds,
                'stuck_processing' => $stuckProcessing,
            ],
            'health' => [
                'gateway' => [
                    'rate_pct' => $gateway['rate_pct'],
                    'sample'   => $gateway['sample'],
                ],
                'provisioning' => [
                    'healthy_count'     => $provisioningHealth['healthy_count'],
                    'active_count'      => $activeCount,
                    'healthy_share_pct' => $healthySharePercent,
                    'open_failures'     => $provisioningFailures,
                ],
                'sms'     => $sms,
                'imports' => [
                    'running' => ImportRun::query()
                        ->where('status', ImportRunStatusEnum::PROCESSING->value)
                        ->count(),
                ],
            ],
        ];
    }

    /**
     * Real gateway failures from the last 24 hours and the success rate they imply.
     *
     * Failed transactions carrying an application lifecycle code (`ORDER_CLOSED`,
     * `STUCK_TIMEOUT`) are neither failures nor part of the rate denominator.
     *
     * @return array{count: int, sample: int, rate_pct: float|null}
     */
    private function gatewayStats(): array
    {
        $row = (array) PaymentTransaction::query()
            ->where('created_at', '>=', CarbonImmutable::now()->subHours(self::GATEWAY_WINDOW_HOURS))
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed_count',
                [PaymentTransactionStatusEnum::COMPLETED->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND (error_code IS NULL OR error_code NOT IN (?, ?)) THEN 1 ELSE 0 END) AS failed_count',
                [
                    PaymentTransactionStatusEnum::FAILED->value,
                    ...PaymentTransactionErrorCodeEnum::getAllValues(),
                ],
            )
            ->toBase()
            ->first();

        $completed = (int) ($row['completed_count'] ?? 0);
        $failed    = (int) ($row['failed_count'] ?? 0);
        $sample    = $completed + $failed;

        return [
            'count'    => $failed,
            'sample'   => $sample,
            'rate_pct' => $sample > 0 ? round($completed / $sample * 100, 1) : null,
        ];
    }

    /**
     * The latest attempt per enrollment and provider still failed or needing manual action.
     */
    private function provisioningFailuresCount(): int
    {
        return ProvisioningAttempt::query()
            ->whereIn('status', [
                ProvisioningAttemptStatusEnum::FAILED->value,
                ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED->value,
            ])
            ->whereNotExists(function (Builder $query): void {
                $query->from('provisioning_attempts as later')
                    ->whereColumn('later.enrollment_id', 'provisioning_attempts.enrollment_id')
                    ->whereColumn('later.provider', 'provisioning_attempts.provider')
                    ->whereColumn('later.sequence', '>', 'provisioning_attempts.sequence');
            })
            ->count();
    }

    /**
     * Active enrollments and how many of them are provisioned healthily.
     *
     * @return array{healthy_count: int, active_count: int}
     */
    private function provisioningHealth(): array
    {
        $row = (array) Enrollment::query()
            ->where('enrollment_status', EnrollmentStatusEnum::ACTIVE->value)
            ->selectRaw('COUNT(*) AS active_count')
            ->selectRaw(
                'SUM(CASE WHEN provisioning_status = ? THEN 1 ELSE 0 END) AS healthy_count',
                [ProvisioningStatusEnum::HEALTHY->value],
            )
            ->toBase()
            ->first();

        return [
            'healthy_count' => (int) ($row['healthy_count'] ?? 0),
            'active_count'  => (int) ($row['active_count'] ?? 0),
        ];
    }

    /**
     * Refunds pending or processing, with their total and oldest creation time.
     *
     * @return array{count: int, amount: int, oldest_created_at: string|null}
     */
    private function pendingRefunds(): array
    {
        $row = (array) Refund::query()
            ->whereIn('status', [
                RefundStatusEnum::PENDING->value,
                RefundStatusEnum::PROCESSING->value,
            ])
            ->selectRaw('COUNT(*) AS aggregate_count')
            ->selectRaw('COALESCE(SUM(amount), 0) AS aggregate_amount')
            ->selectRaw('MIN(created_at) AS oldest_created_at')
            ->toBase()
            ->first();

        return [
            'count'             => (int) ($row['aggregate_count'] ?? 0),
            'amount'            => (int) ($row['aggregate_amount'] ?? 0),
            'oldest_created_at' => $row['oldest_created_at'] ?? null,
        ];
    }

    /**
     * Orders still processing after more than 48 hours.
     *
     * @return array{count: int, oldest_updated_at: string|null}
     */
    private function stuckProcessing(): array
    {
        $row = (array) Order::query()
            ->where('status', OrderStatusEnum::PROCESSING->value)
            ->where('updated_at', '<', CarbonImmutable::now()->subHours(self::STUCK_PROCESSING_HOURS))
            ->selectRaw('COUNT(*) AS aggregate_count')
            ->selectRaw('MIN(updated_at) AS oldest_updated_at')
            ->toBase()
            ->first();

        return [
            'count'             => (int) ($row['aggregate_count'] ?? 0),
            'oldest_updated_at' => $row['oldest_updated_at'] ?? null,
        ];
    }

    /**
     * SMS delivery rate over the last 24 hours, with skipped sends kept out of it.
     *
     * @return array{rate_pct: float|null, sample: int, skipped: int}
     */
    private function smsStats(): array
    {
        $row = (array) SmsLog::query()
            ->where('sent_at', '>=', CarbonImmutable::now()->subHours(self::SMS_WINDOW_HOURS))
            ->selectRaw('SUM(CASE WHEN status <> 0 THEN 1 ELSE 0 END) AS sample_count')
            ->selectRaw('SUM(CASE WHEN status >= 200 AND status < 300 THEN 1 ELSE 0 END) AS delivered_count')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS skipped_count',
                [SmsLog::STATUS_SKIPPED],
            )
            ->toBase()
            ->first();

        $sample    = (int) ($row['sample_count'] ?? 0);
        $delivered = (int) ($row['delivered_count'] ?? 0);

        return [
            'rate_pct' => $sample > 0 ? round($delivered / $sample * 100, 1) : null,
            'sample'   => $sample,
            'skipped'  => (int) ($row['skipped_count'] ?? 0),
        ];
    }
}

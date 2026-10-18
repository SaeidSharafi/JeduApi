<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\CarbonImmutable;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Builds the summary block: five period KPIs and the daily revenue/orders trend, with the previous
 * period for comparison and a Jalali day axis.
 */
final class DashboardSummaryReport
{
    /**
     * The summary block payload. Dates are Gregorian in the cache and turned into Jalali by
     * the response DTOs, so a cached entry survives a change of the Jalali formatter.
     *
     * Four aggregate queries: one daily net-revenue series, one window pass for paid orders
     * and pending payments, and one first-enrollment pass. No Order model is ever hydrated
     * (`Order` eager-loads its payments).
     *
     * @return array{
     *     period: array{key: string, start: string, end: string, previous_start: string, previous_end: string},
     *     kpis: array{
     *         net_revenue: array{value: int, previous: int, change_pct: float|null},
     *         paid_orders: array{value: int, previous: int, change_pct: float|null},
     *         aov: array{value: int, previous: int, change_pct: float|null},
     *         new_learners: array{value: int, previous: int, change_pct: float|null},
     *         unfinished_payments: array{value: int, previous: int, change_pct: float|null, amount: int, previous_amount: int},
     *     },
     *     trend: array{
     *         start: string,
     *         end: string,
     *         previous_start: string,
     *         previous_end: string,
     *         points: list<array{date: string, revenue: int, orders: int}>,
     *         previous_points: list<array{date: string, revenue: int, orders: int}>,
     *     },
     * }
     */
    public function build(DashboardPeriod $period): array
    {
        $coverage = $this->coverage($period);
        $current  = $period->current();
        $previous = $period->previous();

        $revenueByDay = [];
        $ordersByDay  = [];

        foreach ($this->completedOrderPaymentDays($coverage) as $row) {
            $day                = $this->toJalaliDay((string) $row->day);
            $revenueByDay[$day] = (int) $row->amount;
            $ordersByDay[$day]  = (int) $row->paid_orders;
        }

        foreach ($this->completedRefundDays($coverage) as $row) {
            $day                = $this->toJalaliDay((string) $row->day);
            $revenueByDay[$day] = ($revenueByDay[$day] ?? 0) - (int) $row->amount;
        }

        $paymentKpis   = $this->paymentKpis($coverage, $current, $previous);
        $learnerCounts = $this->learnerCounts($coverage, $current, $previous);

        $netRevenue = [
            'current'  => $this->sumDays($revenueByDay, $current),
            'previous' => $this->sumDays($revenueByDay, $previous),
        ];
        $paidOrders = [
            'current'  => $paymentKpis['current_paid_orders'],
            'previous' => $paymentKpis['previous_paid_orders'],
        ];
        $averageOrderValue = [
            'current'  => $this->averageOrderValue($netRevenue['current'], $paidOrders['current']),
            'previous' => $this->averageOrderValue($netRevenue['previous'], $paidOrders['previous']),
        ];
        $learners = [
            'current'  => $learnerCounts['current'],
            'previous' => $learnerCounts['previous'],
        ];

        return [
            'period' => [
                'key'            => $period->key(),
                'start'          => $current->start->toIso8601String(),
                'end'            => $current->end->toIso8601String(),
                'previous_start' => $previous->start->toIso8601String(),
                'previous_end'   => $previous->end->toIso8601String(),
            ],
            'kpis' => [
                'net_revenue'         => $this->kpi($netRevenue['current'], $netRevenue['previous']),
                'paid_orders'         => $this->kpi($paidOrders['current'], $paidOrders['previous']),
                'aov'                 => $this->kpi($averageOrderValue['current'], $averageOrderValue['previous']),
                'new_learners'        => $this->kpi($learners['current'], $learners['previous']),
                'unfinished_payments' => [
                    ...$this->kpi($paymentKpis['current_pending_count'], $paymentKpis['previous_pending_count']),
                    'amount'          => $paymentKpis['current_pending_amount'],
                    'previous_amount' => $paymentKpis['previous_pending_amount'],
                ],
            ],
            'trend' => [
                'start'           => $period->trend()->start->toIso8601String(),
                'end'             => $period->trend()->end->toIso8601String(),
                'previous_start'  => $period->previousTrend()->start->toIso8601String(),
                'previous_end'    => $period->previousTrend()->end->toIso8601String(),
                'points'          => $this->trendPoints($period->trend(), $revenueByDay, $ordersByDay),
                'previous_points' => $this->trendPoints($period->previousTrend(), $revenueByDay, $ordersByDay),
            ],
        ];
    }

    /**
     * One Gregorian day window covering every window the response needs, so each scan is
     * shared instead of repeated per block.
     */
    private function coverage(DashboardPeriod $period): DashboardPeriodWindow
    {
        $windows = [$period->current(), $period->previous(), $period->trend(), $period->previousTrend()];

        $start = $windows[0]->start;
        $end   = $windows[0]->end;

        foreach ($windows as $window) {
            $start = $start->min($window->start);
            $end   = $end->max($window->end);
        }

        return new DashboardPeriodWindow($start, $end);
    }

    /**
     * Daily completed order payments: money collected per Tehran day and the distinct paid
     * orders on that day (free orders never count). Grouping by day keeps the trend and the
     * window totals in one scan; a window sum of money is exact across the day buckets.
     *
     * @return Collection<int, stdClass> rows of `day`, `amount` and `paid_orders`
     */
    private function completedOrderPaymentDays(DashboardPeriodWindow $coverage): Collection
    {
        return Payment::query()
            ->leftJoin('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.purpose', PaymentPurposeEnum::ORDER)
            ->where('payments.status', PaymentStatusEnum::COMPLETED)
            ->whereBetween('payments.created_at', [$coverage->start, $coverage->end])
            ->selectRaw('DATE(payments.created_at) AS day')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) AS amount')
            ->selectRaw('COUNT(DISTINCT CASE WHEN orders.grand_total > 0 THEN payments.order_id END) AS paid_orders')
            ->groupByRaw('DATE(payments.created_at)')
            ->toBase()
            ->get();
    }

    /**
     * Daily completed refunds dated by their completion moment, falling back to the request
     * time for older rows. `refunds.amount` is already net of `deduction_amount`.
     *
     * @return Collection<int, stdClass> rows of `day` and `amount`
     */
    private function completedRefundDays(DashboardPeriodWindow $coverage): Collection
    {
        return Refund::query()
            ->where('status', RefundStatusEnum::COMPLETED)
            ->whereRaw(
                'COALESCE(refunds.refunded_at, refunds.created_at) BETWEEN ? AND ?',
                [$coverage->start, $coverage->end],
            )
            ->selectRaw('DATE(COALESCE(refunds.refunded_at, refunds.created_at)) AS day')
            ->selectRaw('COALESCE(SUM(refunds.amount), 0) AS amount')
            ->groupByRaw('DATE(COALESCE(refunds.refunded_at, refunds.created_at))')
            ->toBase()
            ->get();
    }

    /**
     * Paid-order counts and pending-payment totals for both KPI windows in one pass.
     *
     * The select bindings are added in SQL order, ahead of the shared coverage bounds.
     *
     * @return array{
     *     current_paid_orders: int,
     *     previous_paid_orders: int,
     *     current_pending_count: int,
     *     current_pending_amount: int,
     *     previous_pending_count: int,
     *     previous_pending_amount: int,
     * }
     */
    private function paymentKpis(
        DashboardPeriodWindow $coverage,
        DashboardPeriodWindow $current,
        DashboardPeriodWindow $previous,
    ): array {
        $paidOrderCondition = 'payments.status = ? AND payments.purpose = ? AND orders.grand_total > 0 AND payments.created_at BETWEEN ? AND ?';
        $pendingCondition   = 'payments.status = ? AND payments.created_at BETWEEN ? AND ?';

        $completed = PaymentStatusEnum::COMPLETED->value;
        $order     = PaymentPurposeEnum::ORDER->value;
        $pending   = PaymentStatusEnum::PENDING->value;

        $row = (array) Payment::query()
            ->leftJoin('orders', 'orders.id', '=', 'payments.order_id')
            ->whereBetween('payments.created_at', [$coverage->start, $coverage->end])
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN '.$paidOrderCondition.' THEN payments.order_id END) AS current_paid_orders',
                [$completed, $order, $current->start, $current->end],
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN '.$paidOrderCondition.' THEN payments.order_id END) AS previous_paid_orders',
                [$completed, $order, $previous->start, $previous->end],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN '.$pendingCondition.' THEN 1 ELSE 0 END), 0) AS current_pending_count',
                [$pending, $current->start, $current->end],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN '.$pendingCondition.' THEN payments.amount ELSE 0 END), 0) AS current_pending_amount',
                [$pending, $current->start, $current->end],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN '.$pendingCondition.' THEN 1 ELSE 0 END), 0) AS previous_pending_count',
                [$pending, $previous->start, $previous->end],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN '.$pendingCondition.' THEN payments.amount ELSE 0 END), 0) AS previous_pending_amount',
                [$pending, $previous->start, $previous->end],
            )
            ->toBase()
            ->first();

        return [
            'current_paid_orders'     => (int) ($row['current_paid_orders'] ?? 0),
            'previous_paid_orders'    => (int) ($row['previous_paid_orders'] ?? 0),
            'current_pending_count'   => (int) ($row['current_pending_count'] ?? 0),
            'current_pending_amount'  => (int) ($row['current_pending_amount'] ?? 0),
            'previous_pending_count'  => (int) ($row['previous_pending_count'] ?? 0),
            'previous_pending_amount' => (int) ($row['previous_pending_amount'] ?? 0),
        ];
    }

    /**
     * Distinct customers whose first Enrollment was created in each window.
     *
     * @return array{current: int, previous: int}
     */
    private function learnerCounts(
        DashboardPeriodWindow $coverage,
        DashboardPeriodWindow $current,
        DashboardPeriodWindow $previous,
    ): array {
        $firstEnrollments = Enrollment::query()
            ->selectRaw('customer_id, MIN(created_at) AS first_at')
            ->groupBy('customer_id')
            ->toBase();

        $row = (array) DB::query()
            ->fromSub($firstEnrollments, 'first_enrollments')
            ->whereRaw('first_at BETWEEN ? AND ?', [$coverage->start, $coverage->end])
            ->selectRaw(
                'COUNT(CASE WHEN first_at BETWEEN ? AND ? THEN 1 END) AS current_count',
                [$current->start, $current->end],
            )
            ->selectRaw(
                'COUNT(CASE WHEN first_at BETWEEN ? AND ? THEN 1 END) AS previous_count',
                [$previous->start, $previous->end],
            )
            ->first();

        return [
            'current'  => (int) ($row['current_count'] ?? 0),
            'previous' => (int) ($row['previous_count'] ?? 0),
        ];
    }

    /**
     * A KPI value paired with the previous window, with the one-decimal change.
     *
     * @return array{value: int, previous: int, change_pct: float|null}
     */
    private function kpi(int $value, int $previous): array
    {
        return [
            'value'      => $value,
            'previous'   => $previous,
            'change_pct' => $this->changePercentage($value, $previous),
        ];
    }

    private function changePercentage(int $value, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round(($value - $previous) / $previous * 100, 1);
    }

    /**
     * Average order value in whole IRR; no paid orders means no average.
     */
    private function averageOrderValue(int $netRevenue, int $paidOrders): int
    {
        return $paidOrders === 0 ? 0 : intdiv($netRevenue, $paidOrders);
    }

    /**
     * One point per day of the window, zero-filled, so the chart never has to fill gaps.
     *
     * @param  array<string, int>  $revenueByDay
     * @param  array<string, int>  $ordersByDay
     * @return list<array{date: string, revenue: int, orders: int}>
     */
    private function trendPoints(
        DashboardPeriodWindow $window,
        array $revenueByDay,
        array $ordersByDay,
    ): array {
        $points = [];

        foreach ($window->days() as $jalaliDay) {
            $points[] = [
                'date'    => Verta::parse($jalaliDay)->toCarbon()->toDateString(),
                'revenue' => $revenueByDay[$jalaliDay] ?? 0,
                'orders'  => $ordersByDay[$jalaliDay]  ?? 0,
            ];
        }

        return $points;
    }

    /**
     * Total of a Jalali-day-keyed map over one window (the map only holds covered days).
     *
     * @param  array<string, int>  $valuesByDay
     */
    private function sumDays(array $valuesByDay, DashboardPeriodWindow $window): int
    {
        $total = 0;

        foreach ($window->days() as $jalaliDay) {
            $total += $valuesByDay[$jalaliDay] ?? 0;
        }

        return $total;
    }

    /**
     * The database stores Tehran wall-clock timestamps, so a grouped day is a Tehran day.
     */
    private function toJalaliDay(string $gregorianDay): string
    {
        return Verta::instance(CarbonImmutable::parse($gregorianDay, DashboardPeriod::TIMEZONE))->format('Y-m-d');
    }
}

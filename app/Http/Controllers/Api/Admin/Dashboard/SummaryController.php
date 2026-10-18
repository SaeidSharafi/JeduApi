<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Dashboard\DashboardPeriodQueryData;
use App\Data\Admin\Dashboard\DashboardSummaryData;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardBlockCache;
use App\Services\Dashboard\DashboardPeriod;
use App\Services\Dashboard\DashboardSummaryReport;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Dashboard
 *
 * @authenticated
 */
final class SummaryController extends Controller
{
    /**
     * Get the period KPIs and the daily revenue/orders trend.
     *
     * Net revenue, paid orders, average order value, new learners and unfinished payments
     * for the selected Jalali period, each with its previous-window value and one-decimal
     * percentage change, plus a zero-filled daily revenue/orders trend for the period and
     * for the window before it.
     *
     * @responseFile 200 resources/responses/admin/dashboard/summary.json
     */
    public function __invoke(
        DashboardPeriodQueryData $query,
        DashboardBlockCache $cache,
        DashboardSummaryReport $report,
    ): ApiResponseInterface {
        Gate::authorize(PermissionEnum::DASHBOARD_FINANCIALS->value);

        $period = DashboardPeriod::resolve($query->period);

        $block = $cache->remember(
            CacheKey::AdminDashboardSummary,
            ['period'    => $period->key()],
            fn (): array => $report->build($period),
            $query->fresh,
        );

        return apiResponse()->success(DashboardSummaryData::from($block['payload']), metadata: $block['metadata']);
    }
}

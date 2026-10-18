<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Dashboard\DashboardMoneyData;
use App\Data\Admin\Dashboard\DashboardPeriodQueryData;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardBlockCache;
use App\Services\Dashboard\DashboardMoneyReport;
use App\Services\Dashboard\DashboardPeriod;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Dashboard
 *
 * @authenticated
 */
final class MoneyController extends Controller
{
    /**
     * Get the money position of the selected period.
     *
     * Splits the paid-order cohort into full settlement and Prepayment deposit, groups the same
     * cohort by product type, counts free orders, and reports the offline remainder collected in
     * person. Free orders never enter the mixes.
     *
     * @responseFile 200 resources/responses/admin/dashboard/money.json
     */
    public function __invoke(
        DashboardPeriodQueryData $query,
        DashboardBlockCache $cache,
        DashboardMoneyReport $report,
    ): ApiResponseInterface {
        Gate::authorize(PermissionEnum::DASHBOARD_FINANCIALS->value);

        $period = DashboardPeriod::resolve($query->period);

        $block = $cache->remember(
            CacheKey::AdminDashboardMoney,
            ['period'    => $period->key()],
            fn (): array => $report->build($period->current()),
            $query->fresh,
        );

        return apiResponse()->success(DashboardMoneyData::from($block['payload']), metadata: $block['metadata']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Dashboard\DashboardLiveData;
use App\Data\Admin\Dashboard\DashboardRefreshQueryData;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardBlockCache;
use App\Services\Dashboard\DashboardLiveReport;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Dashboard
 *
 * @authenticated
 */
final class LiveController extends Controller
{
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
    public function __invoke(
        DashboardRefreshQueryData $query,
        DashboardBlockCache $cache,
        DashboardLiveReport $report,
    ): ApiResponseInterface {
        Gate::authorize(PermissionEnum::DASHBOARD_VIEW->value);

        $block = $cache->remember(
            CacheKey::AdminDashboardLive,
            [],
            fn (): array => $report->snapshot(),
            $query->fresh,
        );

        return apiResponse()->success(DashboardLiveData::from($block['payload']), metadata: $block['metadata']);
    }
}

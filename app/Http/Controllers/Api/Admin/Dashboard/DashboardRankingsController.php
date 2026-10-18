<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Actions\Admin\Dashboard\GenerateDashboardRankingsAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Dashboard\DashboardPeriodQueryData;
use App\Data\Admin\Dashboard\DashboardRankingsData;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardBlockCache;
use App\Services\Dashboard\DashboardPeriod;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Dashboard
 *
 * @authenticated
 */
final class DashboardRankingsController extends Controller
{
    /**
     * Get the top products, categories and vendors of the selected period.
     *
     * Revenue follows the order's paid ratio and is netted by line refunds. A Bundle Purchase
     * counts once as its Bundle product and belongs to the Bundle's vendor, so Bundle Component
     * Order Items never appear. Category revenue overlaps on purpose: a line counts in every
     * category of its productable, so category revenue must not be summed into a period total.
     *
     * @responseFile 200 resources/responses/admin/dashboard/rankings.json
     */
    public function __invoke(
        DashboardPeriodQueryData $query,
        DashboardBlockCache $cache,
        GenerateDashboardRankingsAction $rankings,
    ): ApiResponseInterface {
        Gate::authorize(PermissionEnum::DASHBOARD_FINANCIALS->value);

        $period = DashboardPeriod::resolve($query->period);

        $block = $cache->remember(
            CacheKey::AdminDashboardRankings,
            ['period'    => $period->key()],
            fn (): array => $rankings->handle($period->current()->start, $period->current()->end),
            $query->fresh,
        );

        return apiResponse()->success(DashboardRankingsData::from($block['payload']), metadata: $block['metadata']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Dashboard\DashboardRefreshQueryData;
use App\Data\Admin\Dashboard\DashboardWalletData;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardBlockCache;
use App\Services\Dashboard\DashboardWalletReport;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Dashboard
 *
 * @authenticated
 */
final class WalletController extends Controller
{
    /**
     * Get the wallet liability snapshot.
     *
     * Normal Balance and Gift Balance of active wallets, their sum, and the number of
     * active wallets. This block is a snapshot of now and ignores the `period` parameter.
     *
     * @responseFile 200 resources/responses/admin/dashboard/wallet.json
     */
    public function __invoke(
        DashboardRefreshQueryData $query,
        DashboardBlockCache $cache,
        DashboardWalletReport $report,
    ): ApiResponseInterface {
        Gate::authorize(PermissionEnum::DASHBOARD_FINANCIALS->value);

        $block = $cache->remember(
            CacheKey::AdminDashboardWallet,
            [],
            fn (): array => $report->totals(),
            $query->fresh,
        );

        return apiResponse()->success(DashboardWalletData::from($block['payload']), metadata: $block['metadata']);
    }
}

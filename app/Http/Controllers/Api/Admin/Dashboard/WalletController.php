<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Dashboard\DashboardRefreshQueryData;
use App\Data\Admin\Dashboard\DashboardWalletData;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Enums\Wallet\WalletStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\Dashboard\DashboardBlockCache;
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
    public function __invoke(DashboardRefreshQueryData $query, DashboardBlockCache $cache): ApiResponseInterface
    {
        Gate::authorize(PermissionEnum::DASHBOARD_FINANCIALS->value);

        $block = $cache->remember(
            CacheKey::AdminDashboardWallet,
            [],
            fn (): array => $this->walletTotals(),
            $query->fresh,
        );

        return apiResponse()->success(DashboardWalletData::from($block['payload']), metadata: $block['metadata']);
    }

    /**
     * Wallet liability in a single aggregate query.
     *
     * @return array{normal_balance: int, gift_balance: int, total_liability: int, active_wallets: int}
     */
    private function walletTotals(): array
    {
        $totals = (array) Wallet::query()
            ->where('status', WalletStatusEnum::ACTIVE)
            ->selectRaw('COALESCE(SUM(balance), 0) AS normal_balance')
            ->selectRaw('COALESCE(SUM(gift_balance), 0) AS gift_balance')
            ->selectRaw('COUNT(*) AS active_wallets')
            ->toBase()
            ->first();

        $normalBalance = (int) ($totals['normal_balance'] ?? 0);
        $giftBalance   = (int) ($totals['gift_balance'] ?? 0);

        return [
            'normal_balance'  => $normalBalance,
            'gift_balance'    => $giftBalance,
            'total_liability' => $normalBalance + $giftBalance,
            'active_wallets'  => (int) ($totals['active_wallets'] ?? 0),
        ];
    }
}

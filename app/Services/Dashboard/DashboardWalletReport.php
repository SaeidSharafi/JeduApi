<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\Wallet\WalletStatusEnum;
use App\Models\Wallet;

/** Builds the wallet liability snapshot for active wallets. */
final class DashboardWalletReport
{
    /**
     * @return array{normal_balance: int, gift_balance: int, total_liability: int, active_wallets: int}
     */
    public function totals(): array
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

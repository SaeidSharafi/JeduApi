<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** Wallet liability snapshot: active-wallet balances only, in IRR. */
final class DashboardWalletData extends Data
{
    public function __construct(
        public int $normal_balance,
        public int $gift_balance,
        public int $total_liability,
        public int $active_wallets,
    ) {}
}

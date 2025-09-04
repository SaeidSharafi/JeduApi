<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Models\User;
use Illuminate\Support\Facades\Lang;

class GetWalletBalanceAction
{
    /**
     * Get wallet balance for a user.
     *
     * @throws \Exception
     */
    public function execute(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception(__('validation.custom.user_not_found'));
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            throw new \Exception(__('validation.custom.wallet_not_found'));
        }

        return [
            'user_id' => $user->id,
            'balance' => $wallet->balance,
            'gift_balance' => $wallet->gift_balance,
            'available_balance' => $wallet->getAvailableBalance(),
            'status' => $wallet->status,
        ];
    }
}

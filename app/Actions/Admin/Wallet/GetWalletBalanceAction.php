<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Enums\Wallet\WalletStatusEnum;
use App\Exceptions\Wallet\WalletNotFoundException;
use App\Exceptions\Wallet\WalletUserNotFoundException;
use App\Models\User;
use Exception;

final class GetWalletBalanceAction
{
    /**
     * Get wallet balance for a user.
     *
     * @return array<string, WalletStatusEnum|int>
     *
     * @throws WalletUserNotFoundException|WalletNotFoundException
     */
    public function execute(int $userId): array
    {
        $user = User::find($userId);
        if (! $user) {
            throw new WalletUserNotFoundException($userId);
        }

        $wallet = $user->wallet;
        if (! $wallet) {
            throw new WalletNotFoundException($user->id);
        }

        return [
            'user_id'           => $user->id,
            'balance'           => $wallet->balance,
            'gift_balance'      => $wallet->gift_balance,
            'available_balance' => $wallet->getAvailableBalance(),
            'status'            => $wallet->status,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Data\Admin\Wallet\CreateWalletData;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use App\Models\Wallet;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateWalletAction
{
    /**
     * Create a wallet for a user if not exists.
     *
     * @throws Exception
     */
    public function handle(CreateWalletData $data, User $user): Wallet
    {
        $user->refresh();

        if ($user->wallet) {
            throw ValidationException::withMessages([
                'user_id' => [__('validation.custom.wallet_already_exists')],
            ]);
        }

        return DB::transaction(function () use ($data, $user) {
            return Wallet::create([
                'user_id'      => $user->id,
                'balance'      => $data->balance,
                'gift_balance' => $data->gift_balance,
                'status'       => WalletStatusEnum::from($data->status),
            ]);
        });
    }
}

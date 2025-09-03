<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Data\Wallet\CreateWalletData;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class CreateWalletAction
{
    /**
     * Create a wallet for a user if not exists.
     *
     * @throws \Exception
     */
    public function execute(CreateWalletData $data): Wallet
    {
        $user = User::find($data->user_id);
        if (! $user) {
            throw new \Exception(Lang::get('validation.user_not_found'));
        }
        if ($user->wallet) {
            throw new \Exception(Lang::get('validation.wallet_already_exists'));
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

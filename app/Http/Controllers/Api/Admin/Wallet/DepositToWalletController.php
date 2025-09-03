<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Wallet;

use App\Actions\Admin\Wallet\DepositToWalletAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Wallet\DepositToWalletData;
use App\Data\Wallet\WalletTransactionData;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Support\Facades\Gate;

class DepositToWalletController extends Controller
{
    public function __invoke(DepositToWalletData $data): ApiResponseInterface
    {
        Gate::authorize('deposit', Wallet::class);

        $transaction = app(DepositToWalletAction::class)->execute($data, auth('staff')->user());
        $transaction->load('wallet', 'user','source');

        return response()->created(WalletTransactionData::from($transaction));
    }
}

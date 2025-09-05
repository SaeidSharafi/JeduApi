<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Wallet;

use App\Actions\Admin\Wallet\WithdrawFromWalletAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Wallet\WalletTransactionData;
use App\Data\Admin\Wallet\WithdrawFromWalletData;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Support\Facades\Gate;

class WithdrawFromWalletController extends Controller
{
    public function __invoke(WithdrawFromWalletData $data, Wallet $wallet, WithdrawFromWalletAction $action): ApiResponseInterface
    {
        Gate::authorize('withdrawal', $wallet);

        $transaction = $action->handle($data, auth('staff')->user(),$wallet);

        $transaction->load('wallet', 'user','source');

        return response()->created(WalletTransactionData::from($transaction));
    }
}

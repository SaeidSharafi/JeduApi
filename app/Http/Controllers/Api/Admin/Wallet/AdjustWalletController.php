<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Wallet;

use App\Actions\Admin\Wallet\AdjustWalletAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Wallet\AdjustWalletData;
use App\Data\Wallet\WalletTransactionData;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Support\Facades\Gate;

class AdjustWalletController extends Controller
{
    public function __invoke(AdjustWalletData $data, Wallet $wallet, AdjustWalletAction $action): ApiResponseInterface
    {
        Gate::authorize('adjustment', $wallet);

        $transaction = $action->handle($data, auth('staff')->user(), $wallet);

        $transaction->load('wallet', 'user', 'source');

        return response()->created(WalletTransactionData::from($transaction));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Wallet;

use App\Actions\Admin\Wallet\DepositToWalletAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Wallet\DepositToWalletData;
use App\Data\Admin\Wallet\WalletTransactionData;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Wallet Management
 *
 * @authenticated
 */
final class DepositToWalletController extends Controller
{
    /**
     * Deposit funds to a wallet (admin action).
     *
     * @responseFile 201 responses/wallet/deposit.json
     * @responseFile 422 responses/422.json
     */
    public function __invoke(DepositToWalletData $data, Wallet $wallet, DepositToWalletAction $action): ApiResponseInterface
    {
        Gate::authorize('deposit', $wallet);

        $transaction = $action->handle($data, auth('staff')->user(), $wallet);
        $transaction->load('wallet', 'user', 'source');

        return response()->created(WalletTransactionData::from($transaction));
    }
}

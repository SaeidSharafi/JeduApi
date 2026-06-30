<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Wallet;

use App\Actions\Admin\Wallet\AdjustWalletAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Wallet\AdjustWalletData;
use App\Data\Admin\Wallet\WalletTransactionData;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Wallet Management
 *
 * @authenticated
 */
final class AdjustWalletController extends Controller
{
    /**
     * Adjust a wallet balance (manual correction by admin).
     *
     * @responseFile 201 resources/responses/admin/wallet/adjust.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(AdjustWalletData $data, Wallet $wallet, AdjustWalletAction $action): ApiResponseInterface
    {
        Gate::authorize('adjustment', $wallet);

        $transaction = $action->handle($data, auth('staff')->user(), $wallet);

        $transaction->load('wallet', 'user', 'source');

        return apiResponse()->created(WalletTransactionData::from($transaction));
    }
}

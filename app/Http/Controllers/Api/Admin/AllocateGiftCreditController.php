<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Wallet\AllocateGiftCreditAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Wallet\AllocateGiftCreditData;
use App\Data\Admin\Wallet\GiftAllocationResultData;
use App\Data\Wallet\WalletTransactionData;
use App\Exceptions\CustomValidationException;
use App\Http\Controllers\Controller;
use App\Models\WalletCampaign;
use Illuminate\Support\Facades\Gate;

final class AllocateGiftCreditController extends Controller
{
    /**
     * Allocate gift credit to a user from a campaign
     */
    public function __invoke(
        AllocateGiftCreditData $data,
        WalletCampaign $walletCampaign,
        AllocateGiftCreditAction $action,
    ): ApiResponseInterface {

        Gate::authorize('allocate', $walletCampaign);

        try {
            $transaction = $action->handle($data,$walletCampaign);
            $transaction->load(['wallet', 'user', 'source']);

            return response()->success(data: WalletTransactionData::from($transaction), message: __('messages.gift_credit_allocated_successfully'));
        } catch (CustomValidationException $e) {
            return response()->error(
                message: $e->getMessage(),
                status: 422
            );
        }
    }
}

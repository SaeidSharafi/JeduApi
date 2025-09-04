<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\WalletCampaign\ProcessCampaignBonusAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Wallet\ProcessCampaignBonusData;
use App\Data\Wallet\WalletTransactionData;
use App\Http\Controllers\Controller;
use App\Models\WalletCampaign;
use Illuminate\Support\Facades\Gate;

final class ProcessCampaignBonusController extends Controller
{
    /**
     * Process campaign bonus for a user
     */
    public function __invoke(
        ProcessCampaignBonusData $data,
        WalletCampaign $walletCampaign,
        ProcessCampaignBonusAction $action,
    ): ApiResponseInterface {
        Gate::authorize('processBonus', $walletCampaign);

        try {
            $transaction = $action->handle($data,$walletCampaign);
            $transaction->refresh();
            $transaction
                ->load(['wallet', 'user', 'source']);

            return response()->success(
                data: WalletTransactionData::from($transaction),
                message: __('messages.campaign_bonus_processed_successfully')
            );
        } catch (\Exception $e) {
            return response()->error(
                message: $e->getMessage(),
                errors: ['bonus' => [$e->getMessage()]],
                status: 422
            );
        }
    }
}

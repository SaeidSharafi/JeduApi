<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\WalletCampaign;

use App\Actions\Admin\WalletCampaign\TriggerCampaignAllocationAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Wallet\WalletTransactionData;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Exceptions\CustomValidationException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletCampaign;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Wallet Campaign Management
 *
 * @authenticated
 */
final class TriggerCampaignAllocationController extends Controller
{
    /**
     * Trigger a campaign allocation for a specific user (manual or event-based)
     *
     * Allocates a campaign bonus to a user and returns the resulting wallet transaction.
     *
     * @responseFile 200 responses/wallet-campaign/trigger-allocation.json
     * @responseFile 422 responses/422.json
     */
    public function __invoke(
        TriggerCampaignAllocationData $data,
        User $user,
        WalletCampaign $walletCampaign,
        TriggerCampaignAllocationAction $action,
    ): ApiResponseInterface {
        Gate::authorize('allocate', $walletCampaign);

        // Override the user_id in data with the route parameter
        $data = new TriggerCampaignAllocationData(
            trigger_type: $data->trigger_type,
            trigger_event: $data->trigger_event,
            reason: $data->reason,
            metadata: $data->metadata
        );

        try {
            $transaction = $action->handle($data,$user, $walletCampaign);
            $transaction->refresh();
            $transaction->load(['wallet', 'user', 'source']);

            $message = $data->trigger_type === 'manual'
                ? __('messages.gift_credit_allocated_successfully')
                : __('messages.campaign_bonus_processed_successfully');

            return response()->success(
                data: WalletTransactionData::from($transaction),
                message: $message
            );
        } catch (CustomValidationException $e) {
            return response()->error(
                message: $e->getMessage(),
                status: 422
            );
        }
    }
}

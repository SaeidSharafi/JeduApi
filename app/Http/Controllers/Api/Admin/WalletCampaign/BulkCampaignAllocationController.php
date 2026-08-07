<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\WalletCampaign;

use App\Actions\Admin\WalletCampaign\BulkCampaignAllocationAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\WalletCampaign\BulkCampaignAllocationData;
use App\Http\Controllers\Controller;
use App\Models\WalletCampaign;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Wallet Campaign Management
 *
 * @authenticated
 */
final class BulkCampaignAllocationController extends Controller
{
    /**
     * Trigger campaign allocation for multiple users at once.
     *
     * Allocates the selected campaign to a list of users. Returns the number of successful and failed allocations, with details for each user.
     *
     * @responseFile 200 resources/responses/admin/wallet-campaign/bulk-allocation.json
     * @responseFile 207 resources/responses/admin/wallet-campaign/bulk-allocation.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(
        BulkCampaignAllocationData $data,
        WalletCampaign $walletCampaign,
        BulkCampaignAllocationAction $action,
    ): ApiResponseInterface {
        Gate::authorize('allocate', $walletCampaign);

        $result = $action->handle($data, $walletCampaign);

        $message = match (true) {
            $result['failure_count'] === 0 => __('messages.bulk_allocation_completed_successfully', [
                'count' => $result['success_count'],
            ]),
            $result['success_count'] === 0 => __('messages.bulk_allocation_failed_completely'),
            default                        => __('messages.bulk_allocation_completed_partially', [
                'success' => $result['success_count'],
                'failed'  => $result['failure_count'],
            ]),
        };

        $status = $result['failure_count'] === 0 ? 200 : 207; // 207 Multi-Status for partial success

        return apiResponse()->success($result, $message, $status);
    }
}

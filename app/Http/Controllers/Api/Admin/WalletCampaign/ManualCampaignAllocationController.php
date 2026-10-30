<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\WalletCampaign;

use App\Actions\Admin\WalletCampaign\ManualCampaignAllocationAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\WalletCampaign\ManualCampaignAllocationData;
use App\Http\Controllers\Controller;
use App\Models\WalletCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * @group Admin - Wallet Campaign Management
 *
 * @authenticated
 */
final class ManualCampaignAllocationController extends Controller
{
    /**
     * Grant a manual campaign allocation to one or more customers.
     *
     * Allocates a manual_allocation campaign to the listed users. Pass an
     * `Idempotency-Key` header (a UUID) to make retries safe; each user gets
     * its own key derived from it.
     *
     * @responseFile 200 resources/responses/admin/wallet-campaign/allocations.json
     * @responseFile 207 resources/responses/admin/wallet-campaign/allocations.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(
        ManualCampaignAllocationData $data,
        WalletCampaign $walletCampaign,
        ManualCampaignAllocationAction $action,
        Request $request,
    ): ApiResponseInterface {
        Gate::authorize('allocate', $walletCampaign);

        $result = $action->handle($data, $walletCampaign, $this->idempotencyKey($request));

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

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || mb_trim($key) === '') {
            return null;
        }

        $key = mb_trim($key);

        Validator::make(
            ['idempotency_key' => $key],
            ['idempotency_key' => ['required', 'string', 'max:200']],
        )->validate();

        return $key;
    }
}

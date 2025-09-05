<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Data\Admin\WalletCampaign\BulkCampaignAllocationData;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Models\User;
use App\Models\WalletCampaign;
use Illuminate\Support\Facades\DB;

final readonly class BulkCampaignAllocationAction
{
    public function __construct(
        private TriggerCampaignAllocationAction $triggerAction
    ) {
    }

    /**
     * Process bulk campaign allocation for multiple users
     *
     * @param BulkCampaignAllocationData $data
     * @param WalletCampaign $campaign
     * @return array{success_count: int, failure_count: int, results: array}
     */
    public function handle(BulkCampaignAllocationData $data, WalletCampaign $campaign): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        $users = User::query()
            ->find($data->user_ids);
        foreach ($data->user_ids as $userId) {
            try {
                $individualData = new TriggerCampaignAllocationData(
                    trigger_type: $data->trigger_type,
                    trigger_event: $data->trigger_event,
                    reason: $data->reason,
                    metadata: $data->metadata
                );
                $user = $users->firstWhere('id', $userId);
                // @codeCoverageIgnoreStart
                if (!$user){
                    throw new \Exception(__('validation.custom.user_not_found'));
                }
                // @codeCoverageIgnoreEnd
                $transaction = $this->triggerAction->handle($individualData,$user, $campaign);

                $results[] = [
                    'user_id' => $userId,
                    'status' => 'success',
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                ];

                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'user_id' => $userId,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                $failureCount++;
            }
        }

        return [
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results,
        ];
    }

    /**
     * Process bulk allocation in a database transaction for atomicity
     *
     * @codeCoverageIgnore
     */
    public function handleAtomic(BulkCampaignAllocationData $data, WalletCampaign $campaign): array
    {
        return DB::transaction(function () use ($data, $campaign) {
            return $this->handle($data, $campaign);
        });
    }
}

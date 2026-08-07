<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Data\Admin\WalletCampaign\BulkCampaignAllocationData;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Exceptions\Wallet\WalletNotFoundException;
use App\Exceptions\Wallet\WalletUserNotFoundException;
use App\Models\User;
use App\Models\WalletCampaign;
use Illuminate\Support\Facades\DB;

final readonly class BulkCampaignAllocationAction
{
    public function __construct(
        private TriggerCampaignAllocationAction $triggerAction
    ) {}

    /**
     * Process bulk campaign allocation for multiple users
     *
     * @return array{success_count: int, failure_count: int, results: array<int, array<string, mixed>>}
     */
    public function handle(BulkCampaignAllocationData $data, WalletCampaign $campaign): array
    {
        $results      = [];
        $successCount = 0;
        $failureCount = 0;
        $users        = User::query()
            ->with('wallet')
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
                if (! $user) {
                    throw new WalletUserNotFoundException($userId);
                }
                // @codeCoverageIgnoreEnd
                $transaction = $this->triggerAction->handle($individualData, $user, $campaign);

                $results[] = [
                    'user_id'        => $userId,
                    'status'         => 'success',
                    'transaction_id' => $transaction->id,
                    'amount'         => $transaction->amount,
                ];

                $successCount++;

            } catch (CustomValidationException|WalletUserNotFoundException|WalletNotFoundException|WalletInsufficientBalanceException $e) {
                $results[] = [
                    'user_id' => $userId,
                    'status'  => 'failed',
                    'error'   => $e->getMessage(),
                ];

                $failureCount++;
            }
        }

        return [
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results'       => $results,
        ];
    }

    /**
     * Process bulk allocation in a database transaction for atomicity
     *
     * @codeCoverageIgnore
     *
     * @return array{success_count: int, failure_count: int, results: array<int, array<string, mixed>>}
     */
    public function handleAtomic(BulkCampaignAllocationData $data, WalletCampaign $campaign): array
    {
        return DB::transaction(function () use ($data, $campaign): array {
            return $this->handle($data, $campaign);
        });
    }
}

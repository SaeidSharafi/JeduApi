<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Data\Admin\WalletCampaign\ManualCampaignAllocationData;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\WalletCampaign\CampaignTriggerTypeEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Exceptions\Wallet\WalletNotFoundException;
use App\Exceptions\Wallet\WalletUserNotFoundException;
use App\Models\User;
use App\Models\WalletCampaign;

final readonly class ManualCampaignAllocationAction
{
    public function __construct(
        private TriggerCampaignAllocationAction $triggerAction
    ) {}

    /**
     * Grant a manual allocation from a manual_allocation campaign to 1..100 users.
     *
     * @return array{success_count: int, failure_count: int, results: array<int, array<string, mixed>>}
     *
     * @throws CustomValidationException
     */
    public function handle(ManualCampaignAllocationData $data, WalletCampaign $campaign, ?string $idempotencyKey = null): array
    {
        if ($campaign->type !== CampaignTypeEnum::MANUAL_ALLOCATION) {
            throw new CustomValidationException(__('validation.custom.campaign_not_manual_allocation'));
        }

        $results      = [];
        $successCount = 0;
        $failureCount = 0;
        $users        = User::query()
            ->with('wallet')
            ->find($data->user_ids);

        foreach ($data->user_ids as $userId) {
            try {
                $individualData = new TriggerCampaignAllocationData(
                    trigger_type: CampaignTriggerTypeEnum::MANUAL,
                    trigger_event: null,
                    reason: $data->reason,
                    metadata: $data->metadata,
                    idempotency_key: $idempotencyKey !== null
                        ? sprintf('%s:c:%d:u:%d', $idempotencyKey, $campaign->id, $userId)
                        : null,
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
}

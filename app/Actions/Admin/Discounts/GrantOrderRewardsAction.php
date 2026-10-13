<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class GrantOrderRewardsAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordWalletTransaction,
    ) {}

    /**
     * Execute the reward intents snapshotted on the order at creation.
     *
     * The order row is locked for the whole grant, and every ledger write
     * carries a deterministic idempotency key, so repeated settlement (two
     * completion paths, a retry, a double transition) can never pay a reward
     * twice. A reward that cannot be written — most commonly because the
     * customer owns no wallet — is logged and skipped: a paid order must never
     * fail to complete because of a bonus.
     */
    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if (! $lockedOrder instanceof Order) {
                return;
            }

            $intents = $lockedOrder->reward_intents_json ?? [];

            foreach (array_values($intents) as $index => $intent) {
                try {
                    $this->grant($lockedOrder, $intent, $index);
                } catch (Throwable $e) {
                    Log::error('Failed to grant promotion reward', [
                        'error'        => $e->getMessage(),
                        'order_id'     => $lockedOrder->id,
                        'customer_id'  => $lockedOrder->customer_id,
                        'promotion_id' => $intent['promotion_id'] ?? null,
                        'credit_type'  => $intent['type']         ?? null,
                        'amount'       => $intent['amount']       ?? null,
                    ]);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function grant(Order $order, array $intent, int $index): void
    {
        $customerId = $order->customer_id;
        $amount     = (int) ($intent['amount'] ?? 0);

        if ($customerId === null || $amount <= 0) {
            return;
        }

        $type        = TransactionTypeEnum::from($intent['type']);
        $expiresDays = $intent['expires_days'] ?? null;

        $this->recordWalletTransaction->execute(new RecordTransactionData(
            user_id: $customerId,
            type: $type,
            amount: $amount,
            source_type: TransactionSourceEnum::PROMOTION,
            source_id: $intent['promotion_id']  ?? null,
            description: $intent['description'] ?? null,
            metadata: [
                'order_id'       => $order->id,
                'promotion_name' => $intent['promotion_name'] ?? null,
                'credit_type'    => $type === TransactionTypeEnum::GIFT ? 'gift' : 'regular',
                'expires_days'   => $expiresDays,
                'configuration'  => $intent['configuration'] ?? [],
            ],
            // Gift expiry is counted from the day the reward is granted, not
            // from the day the customer happened to browse their cart.
            expires_at: $expiresDays !== null
                ? now()->addDays((int) $expiresDays)->format('Y-m-d H:i:s')
                : null,
            idempotency_key: $this->idempotencyKey($order, $intent, $index),
        ));
    }

    /**
     * The key is derived from the order, the promotion, the credit type and the
     * intent's position in the immutable snapshot, so two rewards from the same
     * promotion are still two distinct grants while a repeated settlement of the
     * same snapshot collapses to a no-op.
     *
     * @param  array<string, mixed>  $intent
     */
    private function idempotencyKey(Order $order, array $intent, int $index): string
    {
        return sprintf(
            'order-reward:%d:%d:%s:%d',
            (int) $order->getKey(),
            (int) ($intent['promotion_id'] ?? 0),
            (string) ($intent['type'] ?? 'unknown'),
            $index,
        );
    }
}

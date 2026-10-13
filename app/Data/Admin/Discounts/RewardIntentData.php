<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use App\Enums\Wallet\TransactionTypeEnum;
use Spatie\LaravelData\Data;

/**
 * A reward a cart promotion intends to grant the customer.
 *
 * Recorded while pricing the cart (no side effects), snapshotted onto the order
 * as orders.reward_intents_json, and executed exactly once when the order
 * reaches COMPLETED.
 */
final class RewardIntentData extends Data
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public int $promotion_id,
        public ?string $promotion_name,
        public TransactionTypeEnum $type,
        public int $amount,
        public string $description,
        public array $configuration = [],
        public ?int $expires_days = null,
    ) {}
}

# Backend Developer Guide - JeduShop Wallet System

## Overview
This comprehensive guide covers the complete wallet system architecture, implementation details, and event-driven campaign system for backend developers working on JeduShop.

## Table of Contents
1. [System Architecture](#1-system-architecture)
2. [Wallet Management System](#2-wallet-management-system)
3. [Discount Promotion System](#3-discount-promotion-system)
4. [Wallet Campaign System](#4-wallet-campaign-system)
5. [Event-Driven Campaign System](#5-event-driven-campaign-system)
6. [Compliance Report System](#6-compliance-report-system)
7. [Suspicious Activity Monitoring](#7-suspicious-activity-monitoring)
8. [Implementation Guides](#8-implementation-guides)
9. [Testing Strategy](#9-testing-strategy)
10. [Best Practices](#10-best-practices)

---

## 1. System Architecture

### 1.1 Database Schema

**Wallets Table:**
```sql
wallets:
- id (bigint primary key)
- user_id (bigint foreign key -> users.id)
- balance (bigint, default 0) -- Main spendable balance
- gift_balance (bigint, default 0) -- Gift credits with expiration
- status (enum: active, suspended, closed)
- created_at, updated_at
```

**Wallet Transactions Table:**
```sql
wallet_transactions:
- id (bigint primary key)
- wallet_id (bigint foreign key -> wallets.id)
- user_id (bigint foreign key -> users.id)
- type (enum: deposit, withdrawal, payment, refund, gift, bonus, adjustment)
- amount (bigint) -- Positive for credits, negative for debits
- balance_after (bigint) -- Snapshot of balance after transaction
- gift_balance_after (bigint) -- Snapshot of gift balance after transaction
- source_type (enum: staff, order, promotion, campaign, system, manual)
- source_id (bigint nullable) -- ID of the source record
- description (text nullable)
- metadata (jsonb nullable)
- expires_at (timestamp nullable) -- For promotional credits
- created_by (bigint nullable foreign key -> staff.id)
- created_at, updated_at
```

**Wallet Campaigns Table:**
```sql
wallet_campaigns:
- id (bigint primary key)
- name (varchar 255)
- description (text nullable)
- type (enum: registration_bonus, birthday_gift, referral_bonus, welcome_gift, loyalty_reward, seasonal_bonus, milestone_reward, manual_allocation)
- amount (bigint) -- Credit amount to allocate
- is_active (boolean default true)
- usage_limit_total (int nullable) -- Total campaign usage limit
- usage_limit_per_user (int nullable) -- Per-user usage limit
- total_usage_count (int default 0) -- Current total usage
- starts_at (timestamp nullable)
- ends_at (timestamp nullable)
- metadata (jsonb nullable) -- Campaign-specific configuration
- created_by (bigint foreign key -> staff.id)
- created_at, updated_at
```

### 1.2 Core Models

**Wallet Model (`app/Models/Wallet.php`):**
```php
class Wallet extends Model
{
    protected $fillable = [
        'user_id', 'balance', 'gift_balance', 'status'
    ];

    protected $casts = [
        'status' => WalletStatusEnum::class,
    ];

    // Relationships
    public function user(): BelongsTo
    public function transactions(): HasMany

    // Business Logic
    public function isActive(): bool
    public function canWithdraw(int $amount): bool
    public function getTotalBalance(): int
}
```

**WalletTransaction Model (`app/Models/WalletTransaction.php`):**
```php
class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id', 'user_id', 'type', 'amount',
        'balance_after', 'gift_balance_after',
        'source_type', 'source_id', 'description',
        'metadata', 'expires_at', 'created_by'
    ];

    protected $casts = [
        'type' => TransactionTypeEnum::class,
        'source_type' => TransactionSourceEnum::class,
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function wallet(): BelongsTo
    public function user(): BelongsTo
    public function source(): MorphTo

    // Business Logic
    public function isCredit(): bool
    public function isDebit(): bool
    public function isGift(): bool
    public function isPromotional(): bool
    public function isExpired(): bool
}
```

---

## 2. Wallet Management System

### 2.1 Core Transaction Action

**RecordWalletTransactionAction (`app/Actions/Wallet/RecordWalletTransactionAction.php`):**
```php
class RecordWalletTransactionAction
{
    public function execute(RecordTransactionData $data): WalletTransaction
    {
        $user = User::find($data->user_id);
        if (!$user) {
            throw new \Exception(__('validation.custom.user_not_found'));
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            throw new \Exception(__('validation.custom.wallet_not_found'));
        }

        return DB::transaction(function () use ($wallet, $user, $data) {
            // Lock wallet to prevent race conditions
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            // Normalize amount based on transaction type
            if ($data->type->isDebit()) {
                $data->amount = -abs($data->amount);
            }
            if ($data->type->isCredit()) {
                $data->amount = abs($data->amount);
            }

            // Calculate new balances
            $newBalance = $wallet->balance + $data->amount;
            $newGiftBalance = $wallet->gift_balance;

            // For gift transactions, update gift balance instead
            if ($data->type->isGift()) {
                $newGiftBalance = $wallet->gift_balance + $data->amount;
                $newBalance = $wallet->balance; // Don't change regular balance
            }

            // Validate balance constraints
            if ($newBalance < 0) {
                throw new \Exception(__('validation.custom.insufficient_balance'));
            }

            // Update wallet balance atomically
            $wallet->update([
                'balance' => $newBalance,
                'gift_balance' => $newGiftBalance,
            ]);

            // Create transaction record
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => $data->type,
                'amount' => $data->amount,
                'balance_after' => $newBalance,
                'gift_balance_after' => $newGiftBalance,
                'source_type' => $data->source_type,
                'source_id' => $data->source_id,
                'description' => $data->description,
                'metadata' => $data->metadata ?? [],
                'expires_at' => $data->expires_at ? now()->parse($data->expires_at) : null,
            ]);
        });
    }
}
```

### 2.2 Admin Wallet Operations

**Deposit Action (`app/Actions/Admin/Wallet/DepositToWalletAction.php`):**
```php
class DepositToWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransactionAction
    ) {}

    public function handle(DepositToWalletData $data, Staff $staff, Wallet $wallet): WalletTransaction
    {
        if (!$wallet->isActive()) {
            throw new \Exception(__('validation.custom.wallet_not_active'));
        }

        return $this->recordTransactionAction->execute(new RecordTransactionData(
            user_id: $wallet->user_id,
            type: TransactionTypeEnum::DEPOSIT,
            amount: $data->amount,
            source_type: TransactionSourceEnum::STAFF,
            source_id: $staff->id,
            description: $data->description,
            metadata: $data->metadata ?? [],
        ));
    }
}
```

**Withdrawal Action (`app/Actions/Admin/Wallet/WithdrawFromWalletAction.php`):**
```php
class WithdrawFromWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransactionAction
    ) {}

    public function handle(WithdrawFromWalletData $data, Staff $staff, Wallet $wallet): WalletTransaction
    {
        if (!$wallet->isActive()) {
            throw new \Exception(__('validation.custom.wallet_not_active'));
        }

        if (!$wallet->canWithdraw($data->amount)) {
            throw new \Exception(__('validation.custom.insufficient_balance'));
        }

        return $this->recordTransactionAction->execute(new RecordTransactionData(
            user_id: $wallet->user_id,
            type: TransactionTypeEnum::WITHDRAWAL,
            amount: $data->amount,
            source_type: TransactionSourceEnum::STAFF,
            source_id: $staff->id,
            description: $data->description,
            metadata: $data->metadata ?? [],
        ));
    }
}
```

**Adjustment Action (`app/Actions/Admin/Wallet/AdjustWalletAction.php`):**
```php
class AdjustWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransactionAction
    ) {}

    public function handle(AdjustWalletData $data, Staff $staff, Wallet $wallet): WalletTransaction
    {
        if (!$wallet->isActive()) {
            throw new \Exception(__('validation.custom.wallet_not_active'));
        }

        // For negative adjustments, check if there's sufficient balance
        if ($data->amount < 0 && !$wallet->canWithdraw(abs($data->amount))) {
            throw new \Exception(__('validation.custom.insufficient_balance'));
        }

        return $this->recordTransactionAction->execute(new RecordTransactionData(
            user_id: $wallet->user_id,
            type: TransactionTypeEnum::ADJUSTMENT,
            amount: $data->amount,
            source_type: TransactionSourceEnum::STAFF,
            source_id: $staff->id,
            description: $data->description,
            metadata: array_merge($data->metadata ?? [], [
                'reason' => $data->reason,
                'adjustment_type' => $data->amount > 0 ? 'credit' : 'debit',
            ]),
        ));
    }
}
```

---

## 3. Discount Promotion System

### 3.1 Add Wallet Credit Action

**AddWalletCreditAction (`app/Services/Discounts/Cart/Actions/AddWalletCreditAction.php`):**
```php
#[DiscountHandlerKey('add_wallet_credit')]
final class AddWalletCreditAction implements DiscountActionContract
{
    public function __construct(
        private readonly RecordWalletTransactionAction $recordWalletTransactionAction
    ) {}

    public static function getConfigClass(): string
    {
        return AddWalletCreditConfigData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        if (!$configuration instanceof AddWalletCreditConfigData) {
            return;
        }

        $customer = $context->customer;
        if (!$customer || !$customer->wallet) {
            return;
        }

        $creditAmount = $this->calculateCreditAmount($context, $configuration);
        if ($creditAmount <= 0) {
            return;
        }

        $description = $configuration->description ?? __('wallet.promotion.credit_from_order', [
            'promotion' => $context->evaluating_promotion?->name ?? __('wallet.promotion.discount')
        ]);

        try {
            $transactionData = new RecordTransactionData(
                user_id: $customer->id,
                type: TransactionTypeEnum::BONUS,
                amount: $creditAmount,
                source_type: TransactionSourceEnum::PROMOTION,
                source_id: $context->evaluating_promotion?->id,
                description: $description,
                metadata: [
                    'order_id' => $context->order_id ?? null,
                    'promotion_name' => $context->evaluating_promotion?->name,
                    'credit_type' => 'regular',
                    'configuration' => $configuration->toArray()
                ]
            );

            $this->recordWalletTransactionAction->execute($transactionData);
        } catch (\Exception $e) {
            \Log::error('Failed to record wallet credit from promotion', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
                'promotion_id' => $context->evaluating_promotion?->id,
                'amount' => $creditAmount
            ]);
        }
    }

    private function calculateCreditAmount(OrderContextData $context, AddWalletCreditConfigData $configuration): int
    {
        if ($configuration->per_item) {
            $eligibleItemsCount = 0;
            foreach ($context->items as $item) {
                if ($item->payment_type !== OrderItemPaymentTypeEnum::PRE_PAYMENT) {
                    $eligibleItemsCount += $item->qty;
                }
            }
            return $configuration->amount * $eligibleItemsCount;
        } else {
            return $configuration->amount;
        }
    }
}
```

### 3.2 Add Gift Credit Action

**AddGiftCreditAction (`app/Services/Discounts/Cart/Actions/AddGiftCreditAction.php`):**
```php
#[DiscountHandlerKey('add_gift_credit')]
final class AddGiftCreditAction implements DiscountActionContract
{
    public function __construct(
        private readonly RecordWalletTransactionAction $recordWalletTransactionAction
    ) {}

    public static function getConfigClass(): string
    {
        return AddGiftCreditConfigData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        if (!$configuration instanceof AddGiftCreditConfigData) {
            return;
        }

        $customer = $context->customer;
        if (!$customer || !$customer->wallet) {
            return;
        }

        $giftAmount = $this->calculateGiftAmount($context, $configuration);
        if ($giftAmount <= 0) {
            return;
        }

        // Calculate expiration date if specified
        $expiresAt = null;
        if ($configuration->expires_days !== null) {
            $expiresAt = now()->addDays($configuration->expires_days)->format('Y-m-d H:i:s');
        }

        $description = $configuration->description ?? __('wallet.promotion.gift_from_order', [
            'promotion' => $context->evaluating_promotion?->name ?? __('wallet.promotion.discount')
        ]);

        try {
            $transactionData = new RecordTransactionData(
                user_id: $customer->id,
                type: TransactionTypeEnum::GIFT,
                amount: $giftAmount,
                source_type: TransactionSourceEnum::PROMOTION,
                source_id: $context->evaluating_promotion?->id,
                description: $description,
                metadata: [
                    'order_id' => $context->order_id ?? null,
                    'promotion_name' => $context->evaluating_promotion?->name,
                    'credit_type' => 'gift',
                    'expires_days' => $configuration->expires_days,
                    'configuration' => $configuration->toArray()
                ],
                expires_at: $expiresAt
            );

            $this->recordWalletTransactionAction->execute($transactionData);
        } catch (\Exception $e) {
            \Log::error('Failed to record gift credit from promotion', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
                'promotion_id' => $context->evaluating_promotion?->id,
                'amount' => $giftAmount
            ]);
        }
    }

    private function calculateGiftAmount(OrderContextData $context, AddGiftCreditConfigData $configuration): int
    {
        if ($configuration->per_item) {
            $eligibleItemsCount = 0;
            foreach ($context->items as $item) {
                if ($item->payment_type !== OrderItemPaymentTypeEnum::PRE_PAYMENT) {
                    $eligibleItemsCount += $item->qty;
                }
            }
            return $configuration->amount * $eligibleItemsCount;
        } else {
            return $configuration->amount;
        }
    }
}
```

---

## 4. Wallet Campaign System

### 4.1 Campaign Allocation Action

**TriggerCampaignAllocationAction (`app/Actions/Admin/Wallet/TriggerCampaignAllocationAction.php`):**
```php
class TriggerCampaignAllocationAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransactionAction
    ) {}

    public function handle(TriggerCampaignAllocationData $data, User $user, WalletCampaign $campaign): WalletTransaction
    {
        // Validate campaign status
        if (!$campaign->allocationStatus($user, $data->trigger_event)) {
            throw new CustomValidationException(__('validation.custom.campaign_allocation_not_allowed'));
        }

        // Determine transaction type based on trigger type
        $transactionType = $data->trigger_type === 'manual' 
            ? TransactionTypeEnum::GIFT 
            : TransactionTypeEnum::BONUS;

        // Prepare metadata
        $metadata = array_merge($data->metadata ?? [], [
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'trigger_type' => $data->trigger_type,
            'trigger_event' => $data->trigger_event,
        ]);

        // Record transaction
        $transaction = $this->recordTransactionAction->execute(new RecordTransactionData(
            user_id: $user->id,
            type: $transactionType,
            amount: $campaign->amount,
            source_type: TransactionSourceEnum::CAMPAIGN,
            source_id: $campaign->id,
            description: $data->reason ?? $this->getDefaultDescription($campaign, $data),
            metadata: $metadata,
        ));

        // Update campaign usage count
        $campaign->increment('total_usage_count');

        // Dispatch event for audit trail and notifications
        WalletCampaignAllocationTriggeredEvent::dispatch(
            $transaction,
            $campaign,
            $user,
            $data->trigger_type
        );

        return $transaction;
    }

    private function getDefaultDescription(WalletCampaign $campaign, TriggerCampaignAllocationData $data): string
    {
        if ($data->trigger_type === 'manual') {
            return __('wallet.campaign.gift_allocated', [
                'amount' => number_format($campaign->amount),
                'campaign' => $campaign->name
            ]);
        } else {
            return __('wallet.campaign.bonus_processed', [
                'amount' => number_format($campaign->amount),
                'campaign' => $campaign->name,
                'event' => $data->trigger_event ?? __('wallet.campaign.manual_trigger')
            ]);
        }
    }
}
```

### 4.2 Bulk Campaign Allocation Action

**BulkCampaignAllocationAction (`app/Actions/Admin/Wallet/BulkCampaignAllocationAction.php`):**
```php
class BulkCampaignAllocationAction
{
    public function __construct(
        private readonly TriggerCampaignAllocationAction $triggerAction
    ) {}

    public function handle(BulkCampaignAllocationData $data, WalletCampaign $campaign): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        $users = User::query()->find($data->user_ids);

        foreach ($data->user_ids as $userId) {
            try {
                $individualData = new TriggerCampaignAllocationData(
                    trigger_type: $data->trigger_type,
                    trigger_event: $data->trigger_event,
                    reason: $data->reason,
                    metadata: $data->metadata
                );

                $user = $users->firstWhere('id', $userId);
                if (!$user) {
                    throw new \Exception(__('validation.custom.user_not_found'));
                }

                $transaction = $this->triggerAction->handle($individualData, $user, $campaign);

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
}
```

---

## 5. Event-Driven Campaign System

### 5.1 Event Architecture

**Base Event Structure:**
```php
// app/Events/Wallet/WalletCampaignAllocationTriggeredEvent.php
class WalletCampaignAllocationTriggeredEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WalletTransaction $transaction,
        public WalletCampaign $campaign,
        public User $user,
        public string $triggerType
    ) {}
}
```

### 5.2 Campaign Event Listener

**CampaignEventListener (`app/Listeners/CampaignEventListener.php`):**
```php
class CampaignEventListener
{
    public function __construct(
        private TriggerCampaignAllocationAction $triggerAction
    ) {}

    /**
     * Handle user registration events
     */
    public function handleUserRegistered(UserRegisteredEvent $event): void
    {
        $this->processCampaignsForEvent($event->user, 'user_registered');
    }

    /**
     * Handle user birthday events
     */
    public function handleUserBirthday(UserBirthdayEvent $event): void
    {
        $this->processCampaignsForEvent($event->user, 'user_birthday');
    }

    /**
     * Handle order completion events
     */
    public function handleOrderCompleted(OrderCompletedEvent $event): void
    {
        $this->processCampaignsForEvent($event->order->customer, 'order_completed', [
            'order_id' => $event->order->id,
            'order_total' => $event->order->total_amount,
        ]);
    }

    /**
     * Process all eligible campaigns for a specific event
     */
    private function processCampaignsForEvent(User $user, string $triggerEvent, array $metadata = []): void
    {
        $eligibleCampaigns = WalletCampaign::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->whereJsonContains('metadata->trigger_events', $triggerEvent)
            ->get();

        foreach ($eligibleCampaigns as $campaign) {
            try {
                $data = new TriggerCampaignAllocationData(
                    trigger_type: 'event',
                    trigger_event: $triggerEvent,
                    reason: null,
                    metadata: $metadata
                );

                $this->triggerAction->handle($data, $user, $campaign);

                \Log::info('Campaign allocation processed successfully', [
                    'user_id' => $user->id,
                    'campaign_id' => $campaign->id,
                    'trigger_event' => $triggerEvent,
                ]);

            } catch (\Exception $e) {
                \Log::warning('Campaign allocation failed', [
                    'user_id' => $user->id,
                    'campaign_id' => $campaign->id,
                    'trigger_event' => $triggerEvent,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

### 5.3 Event Service Provider Registration

**EventServiceProvider (`app/Providers/EventServiceProvider.php`):**
```php
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegisteredEvent::class => [
            CampaignEventListener::class . '@handleUserRegistered',
        ],
        UserBirthdayEvent::class => [
            CampaignEventListener::class . '@handleUserBirthday',
        ],
        OrderCompletedEvent::class => [
            CampaignEventListener::class . '@handleOrderCompleted',
        ],
    ];
}
```

---

## 6. Compliance Report System

The compliance report system provides comprehensive audit capabilities for monitoring wallet transactions, admin activities, and risk assessment to ensure system security and regulatory compliance.

### 6.1 System Architecture

**Purpose:**
- Generate detailed audit reports for specified date ranges
- Analyze transaction patterns and identify suspicious activities
- Provide risk assessment with actionable recommendations
- Track admin actions and their associated risk levels

### 6.2 Core Components

**GenerateComplianceReportAction (`app/Actions/Admin/Audit/GenerateComplianceReportAction.php`):**
```php
class GenerateComplianceReportAction
{
    public function handle(ComplianceReportData $data): array
    {
        $dateFrom = $data->dateFrom;
        $dateTo = $data->dateTo;
        
        $report = [
            'report_period' => [
                'from' => $dateFrom->format('Y-m-d'),
                'to' => $dateTo->format('Y-m-d')
            ],
            'summary' => $this->generateSummary($dateFrom, $dateTo),
            'report_sections' => []
        ];

        if ($data->include_transaction_analysis) {
            $report['report_sections']['transaction_analysis'] = 
                $this->generateTransactionAnalysis($dateFrom, $dateTo);
        }

        if ($data->include_admin_activity) {
            $report['report_sections']['admin_activity'] = 
                $this->generateAdminActivityReport($dateFrom, $dateTo);
        }

        if ($data->include_risk_assessment) {
            $report['report_sections']['risk_assessment'] = 
                $this->generateRiskAssessmentReport($dateFrom, $dateTo);
        }

        return $report;
    }
}
```

### 6.3 Risk Assessment Engine

The risk assessment system analyzes four key risk categories with weighted scoring:

#### 6.3.1 Risk Categories

**1. Transaction Volume Risk (Weight: 30%)**
- Analyzes high-value transactions (≥ 50M IRR)
- **Example Calculation:**
  - Period: 30 days, Total transactions: 1,000
  - High-amount transactions: 150 (≥ 50M IRR each)
  - Percentage: 150 ÷ 1,000 = 15%
  - Risk Level: High (≥ 15% threshold)
  - Risk Score: 80/100

**2. Temporal Risk (Weight: 20%)**
- Identifies transactions during off-hours (22:00 - 06:00)
- **Example Calculation:**
  - Transactions during off-hours: 50
  - Total transactions: 1,000
  - Percentage: 50 ÷ 1,000 = 5%
  - Risk Level: Low (< 10% threshold)
  - Risk Score: 25/100

**3. Pattern Risk (Weight: 25%)**
- Detects round number transactions and metadata-flagged risks
- **Example Calculation:**
  - Round number transactions (1M, 2M, 5M, etc.): 200
  - Metadata flagged high-risk: 30
  - Total pattern risks: 230
  - Percentage: 230 ÷ 1,000 = 23%
  - Risk Level: High (≥ 20% threshold)
  - Risk Score: 85/100

**4. Admin Activity Risk (Weight: 25%)**
- Monitors admin actions and failed operations
- **Example Calculation:**
  - Total admin actions: 100
  - High-risk admin actions: 15
  - Failed admin actions: 8
  - Combined risk percentage: max(15%, 8%) = 15%
  - Risk Level: High (≥ 10% threshold)
  - Risk Score: 75/100

#### 6.3.2 Overall Risk Score Calculation

**Formula:**
```
Overall Risk Score = (
  (Transaction Volume Score × 30%) +
  (Temporal Risk Score × 20%) +
  (Pattern Risk Score × 25%) +
  (Admin Activity Score × 25%)
)
```

**Example:**
```
Score = (80×0.30) + (25×0.20) + (85×0.25) + (75×0.25)
Score = 24 + 5 + 21.25 + 18.75 = 69/100
Risk Level: High Risk (≥ 60)
```

### 6.4 Risk Assessment Implementation

**Risk Factor Calculation Methods:**
```php
private function calculateRiskFactors(Carbon $dateFrom, Carbon $dateTo): array
{
    $transactions = WalletTransaction::query()
        ->whereBetween('created_at', [$dateFrom, $dateTo])
        ->get();

    $adminActions = AdminActionLog::query()
        ->whereBetween('created_at', [$dateFrom, $dateTo])
        ->get();

    return [
        'transaction_volume_risk' => $this->calculateTransactionVolumeRisk($transactions),
        'temporal_risk' => $this->calculateTemporalRisk($transactions),
        'pattern_risk' => $this->calculatePatternRisk($transactions),
        'admin_activity_risk' => $this->calculateAdminActivityRisk($adminActions),
    ];
}

private function calculateTransactionVolumeRisk(Collection $transactions): array
{
    $highAmountThreshold = 50000000; // 50M IRR
    $highAmountTransactions = $transactions->filter(function ($transaction) use ($highAmountThreshold) {
        return abs($transaction->amount) >= $highAmountThreshold;
    });

    $totalTransactions = $transactions->count();
    $highAmountCount = $highAmountTransactions->count();
    $percentage = $totalTransactions > 0 ? ($highAmountCount / $totalTransactions) * 100 : 0;

    return [
        'high_amount_transactions' => $highAmountCount,
        'high_amount_percentage' => round($percentage, 2),
        'risk_level' => $this->determineRiskLevel($percentage, [5, 15]), // Low < 5%, High ≥ 15%
    ];
}
```

### 6.5 Recommendation System

The system generates actionable recommendations based on risk levels:

**Critical Risk (Score ≥ 80):**
- "Immediate investigation required for suspicious transaction patterns"
- Action: "conduct_immediate_audit"

**High Risk (Score 60-79):**
- "Enhanced monitoring recommended for high-value transactions"
- Action: "implement_enhanced_monitoring"

**Medium Risk (Score 40-59):**
- "Review off-hours transaction policies and procedures"
- Action: "review_policies"

**Low Risk (Score < 40):**
- "Continue regular monitoring and maintain current security measures"
- Action: "continue_regular_monitoring"

---

## 7. Suspicious Activity Monitoring

### 7.1 Automated Detection System

**Real-time Monitoring:**
The system continuously monitors wallet transactions and admin activities for suspicious patterns using event-driven detection.

**Detection Triggers:**
```php
// app/Listeners/SuspiciousActivityListener.php
class SuspiciousActivityListener
{
    public function handleWalletTransaction(WalletTransactionCreated $event): void
    {
        $transaction = $event->transaction;
        
        // Check for suspicious patterns
        if ($this->isHighValueTransaction($transaction)) {
            $this->flagSuspiciousActivity($transaction, 'high_value_transaction');
        }
        
        if ($this->isOffHoursTransaction($transaction)) {
            $this->flagSuspiciousActivity($transaction, 'off_hours_transaction');
        }
        
        if ($this->isRapidTransactionSequence($transaction)) {
            $this->flagSuspiciousActivity($transaction, 'rapid_sequence');
        }
    }
}
```

### 7.2 Suspicious Pattern Detection

**1. High-Value Transaction Detection:**
- **Threshold:** ≥ 50,000,000 IRR (500,000 Toman)
- **Example:** User deposits 75,000,000 IRR at 2:30 AM
- **Action:** Automatic flagging + admin notification

**2. Off-Hours Activity Detection:**
- **Time Range:** 22:00 - 06:00 local time
- **Example:** Multiple transactions between 1:00 AM - 4:00 AM
- **Action:** Enhanced scrutiny flag

**3. Round Number Pattern Detection:**
- **Pattern:** Exact amounts like 1M, 2M, 5M, 10M IRR
- **Example:** Sequence of 1,000,000, 2,000,000, 5,000,000 IRR transactions
- **Action:** Pattern analysis flag

**4. Rapid Transaction Sequence:**
- **Pattern:** Multiple transactions within short time windows
- **Example:** 10+ transactions within 5 minutes from same user
- **Action:** Rate limiting + investigation flag

### 7.3 Admin Activity Monitoring

**Risk Level Classification:**

**Low Risk Actions:**
- Profile view operations
- Report generation
- Basic queries
- **Example:** "Admin viewed user wallet balance"

**Medium Risk Actions:**
- User profile modifications
- Permission changes
- Configuration updates
- **Example:** "Admin modified user email address"

**High Risk Actions:**
- Direct wallet balance adjustments
- Large promotional credit allocations
- System configuration changes
- **Example:** "Admin manually adjusted wallet balance by +10,000,000 IRR"

**Critical Risk Actions:**
- Mass user operations
- System-wide setting changes
- Emergency overrides
- **Example:** "Admin performed bulk wallet credit allocation to 1,000+ users"

### 7.4 Alert System Implementation

**Alert Thresholds and Responses:**

```php
// app/Services/Audit/SuspiciousActivityService.php
class SuspiciousActivityService
{
    public function evaluateTransactionRisk(WalletTransaction $transaction): string
    {
        $riskScore = 0;
        
        // Amount-based risk (0-40 points)
        if ($transaction->amount >= 100000000) $riskScore += 40; // 100M+ IRR
        elseif ($transaction->amount >= 50000000) $riskScore += 25; // 50M-100M IRR
        elseif ($transaction->amount >= 10000000) $riskScore += 10; // 10M-50M IRR
        
        // Time-based risk (0-20 points)
        $hour = $transaction->created_at->hour;
        if ($hour >= 22 || $hour <= 6) $riskScore += 20; // Off-hours
        elseif ($hour >= 20 || $hour <= 8) $riskScore += 10; // Extended hours
        
        // Pattern-based risk (0-25 points)
        if ($this->isRoundNumber($transaction->amount)) $riskScore += 15;
        if ($this->isRepeatedPattern($transaction)) $riskScore += 10;
        
        // User history risk (0-15 points)
        if ($this->isUnusualForUser($transaction)) $riskScore += 15;
        
        return $this->getRiskLevel($riskScore);
    }
    
    private function getRiskLevel(int $score): string
    {
        if ($score >= 80) return 'critical';
        if ($score >= 60) return 'high';
        if ($score >= 40) return 'medium';
        return 'low';
    }
}
```

### 7.5 Reporting and Analytics

**Daily Suspicious Activity Summary:**
- Total flagged transactions: 45
- High-risk admin actions: 12
- Off-hours activities: 23
- Pattern anomalies: 18
- **Overall Risk Level:** Medium

**Weekly Trend Analysis:**
- Suspicious activity increased 15% from previous week
- Most common pattern: Round number transactions (65%)
- Peak suspicious hours: 2:00 AM - 4:00 AM
- Top risk category: High-value transactions

**Monthly Compliance Report:**
- Total transactions analyzed: 125,000
- Suspicious activities detected: 890 (0.71%)
- False positive rate: 12%
- Confirmed fraud cases: 3
- **Financial impact prevented:** 450,000,000 IRR

---

## 8. Implementation Guides

### 6.1 Creating New Events

**Step 1: Create Event Class**
```php
// app/Events/UserBirthdayEvent.php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserBirthdayEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user
    ) {}
}
```

**Step 2: Create Console Command for Birthday Check**
```php
// app/Console/Commands/ProcessUserBirthdaysCommand.php
class ProcessUserBirthdaysCommand extends Command
{
    protected $signature = 'users:process-birthdays';
    protected $description = 'Process user birthdays and trigger campaigns';

    public function handle(): void
    {
        $today = now()->format('m-d');
        
        User::query()
            ->whereNotNull('date_of_birth')
            ->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = ?", [$today])
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    UserBirthdayEvent::dispatch($user);
                    $this->info("Birthday event dispatched for user {$user->id}");
                }
            });
    }
}
```

**Step 3: Register Command in Kernel**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('users:process-birthdays')
        ->dailyAt('09:00')
        ->withoutOverlapping();
}
```

### 6.2 Creating User Registration Event

**Step 1: Create Event**
```php
// app/Events/UserRegisteredEvent.php
class UserRegisteredEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $registrationMethod = 'web'
    ) {}
}
```

**Step 2: Dispatch from Registration Controller**
```php
// In your registration logic
class RegisterController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Create wallet for new user
        $user->wallet()->create([
            'balance' => 0,
            'gift_balance' => 0,
            'status' => WalletStatusEnum::ACTIVE,
        ]);

        // Dispatch registration event for campaigns
        UserRegisteredEvent::dispatch($user, 'web');

        return response()->json([
            'message' => 'User registered successfully',
            'user' => UserData::from($user),
        ], 201);
    }
}
```

### 6.3 Creating Campaign Types

**Step 1: Birthday Gift Campaign**
```php
// Campaign creation example
$birthdayGiftCampaign = WalletCampaign::create([
    'name' => 'Birthday Gift Campaign',
    'description' => 'Annual birthday gift for all users',
    'type' => CampaignTypeEnum::BIRTHDAY_GIFT,
    'amount' => 25000, // 250 IRR
    'is_active' => true,
    'usage_limit_per_user' => 1, // Once per year
    'metadata' => [
        'trigger_events' => ['user_birthday'],
        'bonus_type' => 'birthday',
        'annual_reset' => true, // Reset eligibility annually
    ],
    'created_by' => $adminStaff->id,
]);
```

**Step 2: Registration Bonus Campaign**
```php
$registrationBonusCampaign = WalletCampaign::create([
    'name' => 'New User Welcome Bonus',
    'description' => 'Welcome bonus for new registered users',
    'type' => CampaignTypeEnum::REGISTRATION_BONUS,
    'amount' => 50000, // 500 IRR
    'is_active' => true,
    'usage_limit_per_user' => 1, // One-time only
    'metadata' => [
        'trigger_events' => ['user_registered'],
        'bonus_type' => 'welcome',
        'eligibility_period_days' => 7, // Must claim within 7 days
    ],
    'created_by' => $adminStaff->id,
]);
```

### 6.4 Advanced Event Handling

**Order-Based Campaign Example:**
```php
// app/Events/FirstOrderCompletedEvent.php
class FirstOrderCompletedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Order $order
    ) {}
}

// In OrderService or similar
public function completeOrder(Order $order): void
{
    $order->update(['status' => OrderStatusEnum::COMPLETED]);

    // Check if this is user's first completed order
    $isFirstOrder = $order->customer
        ->orders()
        ->where('status', OrderStatusEnum::COMPLETED)
        ->count() === 1;

    if ($isFirstOrder) {
        FirstOrderCompletedEvent::dispatch($order->customer, $order);
    }

    OrderCompletedEvent::dispatch($order);
}
```

### 6.5 Campaign Validation Logic

**Enhanced WalletCampaign Model:**
```php
// app/Models/WalletCampaign.php
class WalletCampaign extends Model
{
    public function allocationStatus(User $user, ?string $triggerEvent = null): bool
    {
        // Check if campaign is active
        if (!$this->is_active) {
            throw new \Exception(__('validation.custom.campaign_inactive'));
        }

        // Check date constraints
        if ($this->starts_at && $this->starts_at->isFuture()) {
            throw new \Exception(__('validation.custom.campaign_not_started'));
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            throw new \Exception(__('validation.custom.campaign_expired'));
        }

        // Check total usage limit
        if ($this->usage_limit_total && $this->total_usage_count >= $this->usage_limit_total) {
            throw new \Exception(__('validation.custom.campaign_usage_limit_exceeded'));
        }

        // Check per-user usage limit
        if ($this->usage_limit_per_user) {
            $userUsageCount = $this->getUserUsageCount($user, $triggerEvent);
            if ($userUsageCount >= $this->usage_limit_per_user) {
                throw new \Exception(__('validation.custom.user_campaign_limit_exceeded'));
            }
        }

        // Check if user has wallet
        if (!$user->wallet || !$user->wallet->isActive()) {
            throw new \Exception(__('validation.custom.wallet_not_active'));
        }

        return true;
    }

    private function getUserUsageCount(User $user, ?string $triggerEvent = null): int
    {
        $query = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('source_type', TransactionSourceEnum::CAMPAIGN)
            ->where('source_id', $this->id);

        // For event-based triggers, count only same trigger events
        if ($triggerEvent) {
            $query->whereJsonContains('metadata->trigger_event', $triggerEvent);
        } else {
            // For manual triggers, count only manual allocations
            $query->whereJsonContains('metadata->trigger_type', 'manual');
        }

        return $query->count();
    }
}
```

---

## 9. Testing Strategy

### 9.1 Unit Tests Example

**TriggerCampaignAllocationActionTest:**
```php
class TriggerCampaignAllocationActionTest extends TestCase
{
    use AuthTestTrait;

    private TriggerCampaignAllocationAction $action;
    private User $user;
    private WalletCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->action = app(TriggerCampaignAllocationAction::class);
        $this->user = User::factory()->create();
        $this->campaign = WalletCampaign::factory()->active()->create([
            'amount' => 25000,
            'usage_limit_per_user' => 2,
        ]);
    }

    public function test_successful_manual_allocation(): void
    {
        $data = new TriggerCampaignAllocationData(
            trigger_type: 'manual',
            trigger_event: null,
            reason: 'Test manual allocation',
            metadata: ['test' => true]
        );

        $transaction = $this->action->handle($data, $this->user, $this->campaign);

        expect($transaction)
            ->toBeInstanceOf(WalletTransaction::class)
            ->and($transaction->type)->toBe(TransactionTypeEnum::GIFT)
            ->and($transaction->amount)->toBe(25000)
            ->and($transaction->user_id)->toBe($this->user->id)
            ->and($transaction->source_id)->toBe($this->campaign->id);

        $this->user->wallet->refresh();
        expect($this->user->wallet->gift_balance)->toBe(25000);

        $this->campaign->refresh();
        expect($this->campaign->total_usage_count)->toBe(1);
    }

    public function test_prevents_duplicate_allocations(): void
    {
        // First allocation
        $data = new TriggerCampaignAllocationData(
            trigger_type: 'event',
            trigger_event: 'user_birthday',
            reason: null,
            metadata: []
        );

        $this->action->handle($data, $this->user, $this->campaign);

        // Second allocation with same trigger event should fail
        expect(fn() => $this->action->handle($data, $this->user, $this->campaign))
            ->toThrow(CustomValidationException::class);
    }

    public function test_allows_different_trigger_events(): void
    {
        // First allocation - birthday
        $birthdayData = new TriggerCampaignAllocationData(
            trigger_type: 'event',
            trigger_event: 'user_birthday',
            reason: null,
            metadata: []
        );

        $this->action->handle($birthdayData, $this->user, $this->campaign);

        // Second allocation - registration (should succeed)
        $registrationData = new TriggerCampaignAllocationData(
            trigger_type: 'event',
            trigger_event: 'user_registered',
            reason: null,
            metadata: []
        );

        expect(fn() => $this->action->handle($registrationData, $this->user, $this->campaign))
            ->not->toThrow(CustomValidationException::class);

        expect($this->user->wallet->fresh()->gift_balance)->toBe(50000);
    }
}
```

### 9.2 Feature Tests Example

**CampaignEventListenerTest:**
```php
class CampaignEventListenerTest extends TestCase
{
    public function test_user_registration_triggers_welcome_campaign(): void
    {
        Event::fake();

        $welcomeCampaign = WalletCampaign::factory()->create([
            'type' => CampaignTypeEnum::WELCOME_GIFT,
            'amount' => 50000,
            'is_active' => true,
            'metadata' => [
                'trigger_events' => ['user_registered']
            ]
        ]);

        $user = User::factory()->create();

        // Dispatch registration event
        UserRegisteredEvent::dispatch($user);

        // Process the event
        $listener = app(CampaignEventListener::class);
        $listener->handleUserRegistered(new UserRegisteredEvent($user));

        // Verify wallet credit was added
        $user->wallet->refresh();
        expect($user->wallet->gift_balance)->toBe(50000);

        // Verify transaction was recorded
        $transaction = WalletTransaction::where('user_id', $user->id)->first();
        expect($transaction)
            ->not->toBeNull()
            ->and($transaction->type)->toBe(TransactionTypeEnum::BONUS)
            ->and($transaction->amount)->toBe(50000)
            ->and($transaction->source_id)->toBe($welcomeCampaign->id);
    }

    public function test_birthday_event_triggers_birthday_campaign(): void
    {
        $birthdayCampaign = WalletCampaign::factory()->create([
            'type' => CampaignTypeEnum::BIRTHDAY_GIFT,
            'amount' => 25000,
            'is_active' => true,
            'usage_limit_per_user' => 1,
            'metadata' => [
                'trigger_events' => ['user_birthday']
            ]
        ]);

        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(25)->format('Y-m-d')
        ]);

        $listener = app(CampaignEventListener::class);
        $listener->handleUserBirthday(new UserBirthdayEvent($user));

        $user->wallet->refresh();
        expect($user->wallet->gift_balance)->toBe(25000);
    }
}
```

### 9.3 Integration Tests

**WalletConcurrencyTest:**
```php
class WalletConcurrencyTest extends TestCase
{
    public function test_concurrent_transactions_maintain_balance_integrity(): void
    {
        $user = User::factory()->create();
        $user->wallet->update(['balance' => 1000]);

        $admin = Staff::factory()->create();

        // Simulate concurrent operations
        $operations = [
            fn() => app(DepositToWalletAction::class)->handle(
                DepositToWalletData::from(['amount' => 100, 'description' => 'Concurrent deposit 1']),
                $admin,
                $user->wallet
            ),
            fn() => app(WithdrawFromWalletAction::class)->handle(
                WithdrawFromWalletData::from(['amount' => 50, 'description' => 'Concurrent withdrawal 1']),
                $admin,
                $user->wallet
            ),
            fn() => app(DepositToWalletAction::class)->handle(
                DepositToWalletData::from(['amount' => 200, 'description' => 'Concurrent deposit 2']),
                $admin,
                $user->wallet
            ),
        ];

        // Execute operations
        $results = [];
        foreach ($operations as $operation) {
            $results[] = $operation();
        }

        // Verify final balance is correct
        $user->refresh();
        $expectedBalance = 1000 + 100 - 50 + 200; // 1250
        expect($user->wallet->balance)->toBe($expectedBalance);

        // Verify all transactions were recorded
        expect($user->wallet->transactions()->count())->toBe(3);
    }
}
```

---

## 10. Best Practices

### 10.1 Database Considerations

**Indexing Strategy:**
```sql
-- Performance indexes
CREATE INDEX idx_wallet_transactions_user_source ON wallet_transactions(user_id, source_type, source_id);
CREATE INDEX idx_wallet_transactions_type_created ON wallet_transactions(type, created_at);
CREATE INDEX idx_wallet_campaigns_active_dates ON wallet_campaigns(is_active, starts_at, ends_at);
CREATE INDEX idx_wallet_transactions_expires_at ON wallet_transactions(expires_at) WHERE expires_at IS NOT NULL;
```

**Database Locks:**
```php
// Always use row-level locking for wallet operations
$wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

// Use database transactions for atomic operations
DB::transaction(function () use ($wallet, $data) {
    // Wallet operations here
});
```

### 10.2 Error Handling

**Custom Exception Classes:**
```php
// app/Exceptions/WalletExceptions.php
class InsufficientBalanceException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('validation.custom.insufficient_balance'));
    }
}

class CampaignNotEligibleException extends Exception
{
    public function __construct(string $reason)
    {
        parent::__construct(__('validation.custom.campaign_not_eligible', ['reason' => $reason]));
    }
}
```

### 10.3 Logging Strategy

**Comprehensive Logging:**
```php
// Log all wallet transactions
\Log::info('Wallet transaction recorded', [
    'user_id' => $user->id,
    'transaction_id' => $transaction->id,
    'type' => $transaction->type->value,
    'amount' => $transaction->amount,
    'balance_after' => $transaction->balance_after,
    'source_type' => $transaction->source_type->value,
    'source_id' => $transaction->source_id,
]);

// Log campaign allocations
\Log::info('Campaign allocation triggered', [
    'user_id' => $user->id,
    'campaign_id' => $campaign->id,
    'trigger_type' => $data->trigger_type,
    'trigger_event' => $data->trigger_event,
    'amount' => $campaign->amount,
]);

// Log errors with context
\Log::error('Campaign allocation failed', [
    'user_id' => $user->id,
    'campaign_id' => $campaign->id,
    'error' => $e->getMessage(),
    'context' => $data->toArray(),
]);
```

### 10.4 Performance Optimization

**Queue Heavy Operations:**
```php
// For bulk operations, use queues
class ProcessBulkCampaignAllocationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $userIds,
        private int $campaignId,
        private TriggerCampaignAllocationData $data
    ) {}

    public function handle(): void
    {
        $campaign = WalletCampaign::find($this->campaignId);
        
        foreach (array_chunk($this->userIds, 50) as $chunk) {
            ProcessCampaignChunk::dispatch($chunk, $campaign, $this->data);
        }
    }
}
```

**Cache Campaign Eligibility:**
```php
// Cache frequently accessed campaign data
public function getEligibleCampaigns(User $user): Collection
{
    return Cache::remember(
        "user_eligible_campaigns_{$user->id}",
        now()->addMinutes(30),
        fn() => $this->calculateEligibleCampaigns($user)
    );
}
```

### 10.5 Security Considerations

**Input Validation:**
```php
// Always validate amounts
public function validateAmount(int $amount): void
{
    if ($amount <= 0) {
        throw new InvalidArgumentException('Amount must be positive');
    }
    
    if ($amount > 10000000) { // 100,000 IRR max per transaction
        throw new InvalidArgumentException('Amount exceeds maximum allowed');
    }
}

// Validate user permissions
Gate::authorize('wallet.manage', $user);
```

**Audit Trail:**
```php
// Maintain comprehensive audit logs
class WalletAuditLog extends Model
{
    protected $fillable = [
        'user_id', 'admin_id', 'action', 'old_values', 
        'new_values', 'ip_address', 'user_agent'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
}
```

This comprehensive backend guide provides all necessary information for implementing and maintaining the wallet system effectively. Each section includes practical examples and best practices to ensure robust, scalable, and secure implementation.

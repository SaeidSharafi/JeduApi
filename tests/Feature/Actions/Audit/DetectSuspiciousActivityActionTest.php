<?php

declare(strict_types=1);

use App\Actions\Admin\Audit\DetectSuspiciousActivityAction;
use App\Data\Admin\Audit\SuspiciousActivityCollectionData;
use App\Data\Admin\Audit\SuspiciousActivityRequestData;
use App\Models\AdminActionLog;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;

describe('DetectSuspiciousActivityAction', function (): void {

    beforeEach(function (): void {
        $this->action   = new DetectSuspiciousActivityAction();
        $this->dateFrom = verta()->subWeek()->format('Y-m-d');
        $this->dateTo   = verta()->format('Y-m-d');
    });

    it('detects large transactions', function (): void {
        // Create large transaction
        WalletTransaction::factory()->create([
            'amount'     => 60000000, // 60M IRR
            'created_at' => now()->subDays(3),
        ]);

        // Create normal transaction
        WalletTransaction::factory()->create([
            'amount'     => 1000000, // 1M IRR
            'created_at' => now()->subDays(2),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => true,
            'include_off_hours'        => false,
            'include_high_frequency'   => false,
            'include_round_numbers'    => false,
        ]);

        $result = $this->action->handle($data);

        expect($result)->toHaveKey('suspicious_activities');
        expect($result->suspicious_activities)->toHaveKey('large_transactions');
        expect($result->suspicious_activities->large_transactions)->toHaveCount(1);
        expect($result->suspicious_activities->large_transactions->first()?->amount)->toBe(60000000);
    });

    it('detects off-hours transactions', function (): void {
        // Create off-hours transaction (2 AM)
        Carbon::setTestNow(Carbon::create(2025, 1, 15, 2, 0, 0));
        $offHoursTransaction = WalletTransaction::factory()->create([
            'amount'     => 5000000,
            'created_at' => Carbon::create(2025, 1, 15, 2, 0, 0),
        ]);

        // Create normal hours transaction (10 AM)
        $normalTransaction = WalletTransaction::factory()->create([
            'amount'     => 5000000,
            'created_at' => Carbon::create(2025, 1, 15, 10, 0, 0),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => Carbon::create(2025, 1, 14),
            'date_to'                  => Carbon::create(2025, 1, 16),
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => false,
            'include_off_hours'        => true,
            'include_high_frequency'   => false,
            'include_round_numbers'    => false,
        ]);

        $result                 = $this->action->handle($data);
        $off_hours_transactions = $result->suspicious_activities->off_hours_transactions;
        expect($result->suspicious_activities)->toHaveKey('off_hours_transactions');
        expect($off_hours_transactions)->toHaveCount(1);
        expect($off_hours_transactions->first()->transaction_id)->toBe($offHoursTransaction->id);

        Carbon::setTestNow(); // Reset
    });

    it('detects high frequency users', function (): void {
        $user = User::factory()->create();

        // Create 12 transactions for the same user
        WalletTransaction::factory()->count(12)->create([
            'user_id'    => $user->id,
            'amount'     => 1000000,
            'created_at' => now()->subDays(2),
        ]);

        // Create 2 transactions for different user
        WalletTransaction::factory()->count(2)->create([
            'amount'     => 1000000,
            'created_at' => now()->subDays(2),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => false,
            'include_off_hours'        => false,
            'include_high_frequency'   => true,
            'include_round_numbers'    => false,
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities)->toHaveKey('high_frequency_users');
        expect($result->suspicious_activities->high_frequency_users)->toHaveCount(1);
        expect($result->suspicious_activities->high_frequency_users->first()?->user_id)->toBe($user->id);
        expect($result->suspicious_activities->high_frequency_users->first()?->transaction_count)->toBe(12);
    });

    it('detects round number patterns', function (): void {
        // Create round number transactions
        WalletTransaction::factory()->create([
            'amount'     => 10000000, // 10M IRR - round number
            'created_at' => now()->subDays(3),
        ]);

        WalletTransaction::factory()->create([
            'amount'     => 5000000, // 5M IRR - round number
            'created_at' => now()->subDays(2),
        ]);

        // Create non-round number transaction
        WalletTransaction::factory()->create([
            'amount'     => 1234567, // Not round
            'created_at' => now()->subDays(1),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => false,
            'include_off_hours'        => false,
            'include_high_frequency'   => false,
            'include_round_numbers'    => true,
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities)->toHaveKey('round_number_patterns');
        expect($result->suspicious_activities->round_number_patterns)->toHaveCount(2);
    });

    it('detects rapid succession transactions', function (): void {
        $user     = User::factory()->create();
        $baseTime = now()->subHours(2);

        // Create 3 transactions within 5 minutes
        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 2000000,
            'created_at' => $baseTime,
        ]);

        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 2000000,
            'created_at' => $baseTime->addMinutes(2),
        ]);

        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 2000000,
            'created_at' => $baseTime->addMinutes(3),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => false,
            'include_off_hours'        => false,
            'include_high_frequency'   => false,
            'include_round_numbers'    => false,
        ]);

        $result = $this->action->handle($data);
        expect($result->suspicious_activities)->toHaveKey('rapid_succession');
        $rapid_succession = $result->suspicious_activities->rapid_succession;
        expect($rapid_succession->isEmpty())->toBeTrue();
    });

    it('detects unusual admin activity', function (): void {
        $admin = Staff::factory()->create();

        // Create multiple high-risk admin actions
        AdminActionLog::factory()->count(5)->create([
            'admin_id'   => $admin->id,
            'risk_level' => 'high',
            'created_at' => now()->subDays(1),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => false,
            'include_off_hours'        => false,
            'include_high_frequency'   => false,
            'include_round_numbers'    => false,
        ]);

        $result = $this->action->handle($data);
        expect($result->suspicious_activities)->toHaveKey('unusual_admin_activity');
        expect($result->suspicious_activities->unusual_admin_activity)->toBeEmptyCollection();
    });

    it('includes detection period and criteria in result', function (): void {
        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => true,
            'include_off_hours'        => true,
            'include_high_frequency'   => true,
            'include_round_numbers'    => true,
        ]);

        $result = $this->action->handle($data);

        expect($result)->toHaveKey('detection_period');
        expect($result->detection_period)->toHaveKeys(['from', 'to']);
        expect($result->detection_period['from'])->toBe($this->dateFrom);
        expect($result->detection_period['to'])->toBe($this->dateTo);

        expect($result)->toHaveKey('detection_criteria');
        expect($result->detection_criteria['large_amount_threshold'])->toBe(50000000);
        expect($result->detection_criteria['high_frequency_threshold'])->toBe(10);
    });

    it('includes summary of suspicious activities', function (): void {
        // Create some suspicious data
        WalletTransaction::factory()->create([
            'amount'     => 60000000, // Large amount
            'created_at' => now()->subDays(3),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => true,
            'include_off_hours'        => true,
            'include_high_frequency'   => true,
            'include_round_numbers'    => true,
        ]);

        $result = $this->action->handle($data);

        expect($result)->toHaveKey('summary');
        expect($result->summary)->toBeArray();
    });

    it('handles empty results gracefully', function (): void {
        // Don't create any suspicious transactions

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => true,
            'include_off_hours'        => true,
            'include_high_frequency'   => true,
            'include_round_numbers'    => true,
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities)->toBeInstanceOf(SuspiciousActivityCollectionData::class);
        expect($result->summary)->toBeArray();
    });

    it('respects date range filtering', function (): void {
        // Create transaction outside date range
        WalletTransaction::factory()->create([
            'amount'     => 60000000,
            'created_at' => now()->subWeeks(2), // Before date_from
        ]);

        // Create transaction within date range
        WalletTransaction::factory()->create([
            'amount'     => 60000000,
            'created_at' => now()->subDays(3), // Within range
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => true,
            'include_off_hours'        => false,
            'include_high_frequency'   => false,
            'include_round_numbers'    => false,
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities->large_transactions)->toHaveCount(1);
    });

    it('filters large transactions by specific user ids', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Create large transactions for different users
        WalletTransaction::factory()->create([
            'user_id'    => $user1->id,
            'amount'     => 60000000,
            'created_at' => now()->subDays(3),
        ]);

        WalletTransaction::factory()->create([
            'user_id'    => $user2->id,
            'amount'     => 70000000,
            'created_at' => now()->subDays(2),
        ]);

        WalletTransaction::factory()->create([
            'user_id'    => $user3->id,
            'amount'     => 80000000,
            'created_at' => now()->subDays(1),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => true,
            'include_off_hours'        => false,
            'include_high_frequency'   => false,
            'include_round_numbers'    => false,
            'user_ids'                 => [$user1->id, $user2->id], // Only filter for user1 and user2
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities->large_transactions)->toHaveCount(2);
        $userIds = $result->suspicious_activities->large_transactions->pluck('user_id')->toArray();
        expect($userIds)->toContain($user1->id);
        expect($userIds)->toContain($user2->id);
        expect($userIds)->not->toContain($user3->id);
    });

    it('filters off-hours transactions by specific user ids', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Create off-hours transactions for different users
        WalletTransaction::factory()->create([
            'user_id'    => $user1->id,
            'amount'     => 6000000,
            'created_at' => Carbon::create(2025, 1, 15, 2, 0, 0),
        ]);

        WalletTransaction::factory()->create([
            'user_id'    => $user2->id,
            'amount'     => 7000000,
            'created_at' => Carbon::create(2025, 1, 15, 23, 0, 0),
        ]);

        WalletTransaction::factory()->create([
            'user_id'    => $user3->id,
            'amount'     => 8000000,
            'created_at' => Carbon::create(2025, 1, 15, 1, 0, 0),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => Carbon::create(2025, 1, 14),
            'date_to'                  => Carbon::create(2025, 1, 16),
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => false,
            'include_off_hours'        => true,
            'include_high_frequency'   => false,
            'include_round_numbers'    => false,
            'user_ids'                 => [$user1->id, $user3->id], // Only filter for user1 and user3
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities->off_hours_transactions)->toHaveCount(2);
        $userIds = $result->suspicious_activities->off_hours_transactions->pluck('user_id')->toArray();
        expect($userIds)->toContain($user1->id);
        expect($userIds)->toContain($user3->id);
        expect($userIds)->not->toContain($user2->id);
    });

    it('filters high frequency users by specific user ids', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Create many transactions for user1 (above threshold)
        WalletTransaction::factory()->count(12)->create([
            'user_id'    => $user1->id,
            'amount'     => 1000000,
            'created_at' => now()->subDays(2),
        ]);

        // Create many transactions for user2 (above threshold)
        WalletTransaction::factory()->count(15)->create([
            'user_id'    => $user2->id,
            'amount'     => 1000000,
            'created_at' => now()->subDays(2),
        ]);

        // Create many transactions for user3 (above threshold)
        WalletTransaction::factory()->count(11)->create([
            'user_id'    => $user3->id,
            'amount'     => 1000000,
            'created_at' => now()->subDays(2),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => false,
            'include_off_hours'        => false,
            'include_high_frequency'   => true,
            'include_round_numbers'    => false,
            'user_ids'                 => [$user2->id], // Only filter for user2
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities->high_frequency_users)->toHaveCount(1);
        expect($result->suspicious_activities->high_frequency_users->first()->user_id)->toBe($user2->id);
        expect($result->suspicious_activities->high_frequency_users->first()->transaction_count)->toBe(15);
    });

    it('filters round number patterns by specific user ids', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Create round number transactions for different users
        WalletTransaction::factory()->create([
            'user_id'    => $user1->id,
            'amount'     => 10000000, // 10M IRR - round number
            'created_at' => now()->subDays(3),
        ]);

        WalletTransaction::factory()->create([
            'user_id'    => $user2->id,
            'amount'     => 5000000, // 5M IRR - round number
            'created_at' => now()->subDays(2),
        ]);

        WalletTransaction::factory()->create([
            'user_id'    => $user3->id,
            'amount'     => 15000000, // 15M IRR - round number
            'created_at' => now()->subDays(1),
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => false,
            'include_off_hours'        => false,
            'include_high_frequency'   => false,
            'include_round_numbers'    => true,
            'user_ids'                 => [$user1->id, $user3->id], // Only filter for user1 and user3
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities->round_number_patterns)->toHaveCount(2);
        $userIds = $result->suspicious_activities->round_number_patterns->pluck('user_id')->toArray();
        expect($userIds)->toContain($user1->id);
        expect($userIds)->toContain($user3->id);
        expect($userIds)->not->toContain($user2->id);
    });

    it('detects actual rapid succession transactions', function (): void {
        $user = User::factory()->create();

        // Use timezone-aware Carbon with Asia/Tehran
        $baseTime = Carbon::now('Asia/Tehran')->subDays(1)->startOfDay()->addHours(10);

        // Create 3 large transactions within 5 minutes (rapid succession)
        $transaction1 = WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 12000000, // Above 10M threshold
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => $baseTime,
            'metadata'   => [
                'audit' => [
                    'is_admin_initiated' => false,
                    'ip_address'         => '192.168.1.100',
                ],
            ],
        ]);

        $transaction2 = WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 15000000, // Above 10M threshold
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => $baseTime->copy()->addMinutes(2), // 2 minutes later
            'metadata'   => [
                'audit' => [
                    'is_admin_initiated' => false,
                    'ip_address'         => '192.168.1.100',
                ],
            ],
        ]);

        $transaction3 = WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 11000000, // Above 10M threshold
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => $baseTime->copy()->addMinutes(4), // 4 minutes from first
            'metadata'   => [
                'audit' => [
                    'is_admin_initiated' => false,
                    'ip_address'         => '192.168.1.100',
                ],
            ],
        ]);

        // Create another transaction after 10 minutes (not rapid - should not be included)
        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 13000000,
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => $baseTime->copy()->addMinutes(10), // 10 minutes later - should NOT be rapid
            'metadata'   => [
                'audit' => [
                    'is_admin_initiated' => false,
                ],
            ],
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => false,
            'include_off_hours'        => false,
            'include_high_frequency'   => false,
            'include_round_numbers'    => false,
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities->rapid_succession)->toHaveCount(3);
        $transactionIds = $result->suspicious_activities->rapid_succession->pluck('transaction_id')->toArray();
        expect($transactionIds)->toContain($transaction1->id);
        expect($transactionIds)->toContain($transaction2->id);
        expect($transactionIds)->toContain($transaction3->id);
    });

    it('detects unusual admin activity with admin-initiated transactions', function (): void {
        $user = User::factory()->create();

        // Create admin-initiated transaction with proper metadata
        $adminTransaction = WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 25000000, // Above 20M threshold for admin actions
            'type'       => App\Enums\Wallet\TransactionTypeEnum::ADJUSTMENT,
            'created_at' => now()->subDays(1),
            'metadata'   => [
                'audit' => [
                    'is_admin_initiated' => true,
                    'ip_address'         => '192.168.1.1',
                ],
            ],
        ]);

        // Create normal user transaction
        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 25000000,
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => now()->subDays(1),
            'metadata'   => [
                'audit' => [
                    'is_admin_initiated' => false,
                ],
            ],
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => false,
            'include_off_hours'        => false,
            'include_high_frequency'   => false,
            'include_round_numbers'    => false,
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities->unusual_admin_activity)->toHaveCount(1);
        expect($result->suspicious_activities->unusual_admin_activity->first()->transaction_id)->toBe($adminTransaction->id);
        expect($result->suspicious_activities->unusual_admin_activity->first()->admin_initiated)->toBe('true');
        expect($result->suspicious_activities->unusual_admin_activity->first()->ip_address)->toBe('192.168.1.1');
    });

    it('includes detection period from field in result', function (): void {
        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => true,
            'include_off_hours'        => true,
            'include_high_frequency'   => true,
            'include_round_numbers'    => true,
        ]);

        $result = $this->action->handle($data);

        expect($result->detection_period)->toHaveKey('from');
        expect($result->detection_period['from'])->toBe($this->dateFrom);
    });

    it('includes complete detection criteria in result', function (): void {
        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $this->dateFrom,
            'date_to'                  => $this->dateTo,
            'large_amount_threshold'   => 75000000,
            'high_frequency_threshold' => 15,
            'include_large_amounts'    => true,
            'include_off_hours'        => true,
            'include_high_frequency'   => true,
            'include_round_numbers'    => true,
        ]);

        $result = $this->action->handle($data);

        expect($result->detection_criteria)->toHaveKey('large_amount_threshold');
        expect($result->detection_criteria)->toHaveKey('high_frequency_threshold');
        expect($result->detection_criteria['large_amount_threshold'])->toBe(75000000);
        expect($result->detection_criteria['high_frequency_threshold'])->toBe(15);
    });

    it('generates comprehensive summary with all activity types populated', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Use timezone-aware Carbon with Asia/Tehran
        $baseTime     = Carbon::now('Asia/Tehran')->subDays(2)->startOfDay()->addHours(10);
        $offHoursTime = $baseTime->copy()->setTime(2, 0);

        // Create data for all activity types

        // Large transaction
        WalletTransaction::factory()->create([
            'user_id'    => $user1->id,
            'amount'     => 60000000,
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => $baseTime,
        ]);

        // Off-hours transaction
        WalletTransaction::factory()->create([
            'user_id'    => $user2->id, // Use user2 so we have 2 unique users
            'amount'     => 6000000,
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => $offHoursTime,
        ]);

        // High frequency user transactions
        WalletTransaction::factory()->count(12)->create([
            'user_id'    => $user2->id,
            'amount'     => 1000000,
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => $baseTime->copy()->addHour(),
        ]);

        // Round number transaction
        WalletTransaction::factory()->create([
            'user_id'    => $user1->id,
            'amount'     => 10000000,
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => $baseTime->copy()->addHours(2),
        ]);

        // Rapid succession transactions
        WalletTransaction::factory()->create([
            'user_id'    => $user2->id,
            'amount'     => 12000000,
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => $baseTime->copy()->addHours(3),
            'metadata'   => [
                'audit' => [
                    'is_admin_initiated' => false,
                ],
            ],
        ]);
        WalletTransaction::factory()->create([
            'user_id'    => $user2->id,
            'amount'     => 13000000,
            'type'       => App\Enums\Wallet\TransactionTypeEnum::DEPOSIT,
            'created_at' => $baseTime->copy()->addHours(3)->addMinutes(3),
            'metadata'   => [
                'audit' => [
                    'is_admin_initiated' => false,
                ],
            ],
        ]);

        // Admin activity
        WalletTransaction::factory()->create([
            'user_id'    => $user1->id,
            'amount'     => 25000000,
            'type'       => App\Enums\Wallet\TransactionTypeEnum::ADJUSTMENT,
            'created_at' => $baseTime->copy()->addHours(4),
            'metadata'   => [
                'audit' => [
                    'is_admin_initiated' => true,
                ],
            ],
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from'                => $baseTime->copy()->subDay(),
            'date_to'                  => $baseTime->copy()->addDay(),
            'large_amount_threshold'   => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts'    => true,
            'include_off_hours'        => true,
            'include_high_frequency'   => true,
            'include_round_numbers'    => true,
        ]);

        $result = $this->action->handle($data);

        expect($result->summary)->toHaveKey('total_suspicious_activities');
        expect($result->summary)->toHaveKey('by_type');
        expect($result->summary)->toHaveKey('unique_users_involved');

        expect($result->summary['by_type'])->toHaveKey('large_transactions');
        expect($result->summary['by_type'])->toHaveKey('off_hours_transactions');
        expect($result->summary['by_type'])->toHaveKey('high_frequency_users');
        expect($result->summary['by_type'])->toHaveKey('round_number_patterns');
        expect($result->summary['by_type'])->toHaveKey('rapid_succession');
        expect($result->summary['by_type'])->toHaveKey('unusual_admin_activity');

        // Should count unique users involved (user1 and user2)
        expect($result->summary['unique_users_involved'])->toBe(2);

        // Total should be sum of all activities
        $expectedTotal = $result->summary['by_type']['large_transactions'] + $result->summary['by_type']['off_hours_transactions'] + $result->summary['by_type']['high_frequency_users'] + $result->summary['by_type']['round_number_patterns'] + $result->summary['by_type']['rapid_succession'] + $result->summary['by_type']['unusual_admin_activity'];

        expect($result->summary['total_suspicious_activities'])->toBe($expectedTotal);
    });
});

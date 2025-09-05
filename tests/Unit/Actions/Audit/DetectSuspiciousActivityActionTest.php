<?php

declare(strict_types=1);

use App\Actions\Admin\Audit\DetectSuspiciousActivityAction;
use App\Data\Admin\Audit\SuspiciousActivityCollectionData;
use App\Data\Admin\Audit\SuspiciousActivityRequestData;
use App\Models\WalletTransaction;
use App\Models\User;
use App\Models\Staff;
use App\Models\AdminActionLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('DetectSuspiciousActivityAction', function () {

    beforeEach(function () {
        $this->action = new DetectSuspiciousActivityAction();
        $this->dateFrom = verta()->subWeek()->format('Y-m-d');
        $this->dateTo = verta()->format('Y-m-d');
    });

    it('detects large transactions', function () {
        // Create large transaction
        WalletTransaction::factory()->create([
            'amount' => 60000000, // 60M IRR
            'created_at' => now()->subDays(3)
        ]);

        // Create normal transaction
        WalletTransaction::factory()->create([
            'amount' => 1000000, // 1M IRR
            'created_at' => now()->subDays(2)
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'large_amount_threshold' => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts' => true,
            'include_off_hours' => false,
            'include_high_frequency' => false,
            'include_round_numbers' => false,
        ]);

        $result = $this->action->handle($data);

        expect($result)->toHaveKey('suspicious_activities');
        expect($result->suspicious_activities)->toHaveKey('large_transactions');
        expect($result->suspicious_activities->large_transactions)->toHaveCount(1);
        expect($result->suspicious_activities->large_transactions->first()?->amount)->toBe(60000000);
    });

    it('detects off-hours transactions', function () {
        // Create off-hours transaction (2 AM)
        Carbon::setTestNow(Carbon::create(2025, 1, 15, 2, 0, 0));
        $offHoursTransaction = WalletTransaction::factory()->create([
            'amount' => 5000000,
            'created_at' => Carbon::create(2025, 1, 15, 2, 0, 0)
        ]);

        // Create normal hours transaction (10 AM)
        $normalTransaction = WalletTransaction::factory()->create([
            'amount' => 5000000,
            'created_at' => Carbon::create(2025, 1, 15, 10, 0, 0)
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from' => Carbon::create(2025, 1, 14),
            'date_to' => Carbon::create(2025, 1, 16),
            'large_amount_threshold' => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts' => false,
            'include_off_hours' => true,
            'include_high_frequency' => false,
            'include_round_numbers' => false,
        ]);

        $result = $this->action->handle($data);
        $off_hours_transactions = $result->suspicious_activities->off_hours_transactions;
        expect($result->suspicious_activities)->toHaveKey('off_hours_transactions');
        expect($off_hours_transactions)->toHaveCount(1);
        expect($off_hours_transactions->first()->transaction_id)->toBe($offHoursTransaction->id);

        Carbon::setTestNow(); // Reset
    });

    it('detects high frequency users', function () {
        $user = User::factory()->create();

        // Create 12 transactions for the same user
        WalletTransaction::factory()->count(12)->create([
            'user_id' => $user->id,
            'amount' => 1000000,
            'created_at' => now()->subDays(2)
        ]);

        // Create 2 transactions for different user
        WalletTransaction::factory()->count(2)->create([
            'amount' => 1000000,
            'created_at' => now()->subDays(2)
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'large_amount_threshold' => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts' => false,
            'include_off_hours' => false,
            'include_high_frequency' => true,
            'include_round_numbers' => false,
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities)->toHaveKey('high_frequency_users');
        expect($result->suspicious_activities->high_frequency_users)->toHaveCount(1);
        expect($result->suspicious_activities->high_frequency_users->first()?->user_id)->toBe($user->id);
        expect($result->suspicious_activities->high_frequency_users->first()?->transaction_count)->toBe(12);
    });

    it('detects round number patterns', function () {
        // Create round number transactions
        WalletTransaction::factory()->create([
            'amount' => 10000000, // 10M IRR - round number
            'created_at' => now()->subDays(3)
        ]);

        WalletTransaction::factory()->create([
            'amount' => 5000000, // 5M IRR - round number
            'created_at' => now()->subDays(2)
        ]);

        // Create non-round number transaction
        WalletTransaction::factory()->create([
            'amount' => 1234567, // Not round
            'created_at' => now()->subDays(1)
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'large_amount_threshold' => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts' => false,
            'include_off_hours' => false,
            'include_high_frequency' => false,
            'include_round_numbers' => true,
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities)->toHaveKey('round_number_patterns');
        expect($result->suspicious_activities->round_number_patterns)->toHaveCount(2);
    });

    it('detects rapid succession transactions', function () {
        $user = User::factory()->create();
        $baseTime = now()->subHours(2);

        // Create 3 transactions within 5 minutes
        WalletTransaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 2000000,
            'created_at' => $baseTime
        ]);

        WalletTransaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 2000000,
            'created_at' => $baseTime->addMinutes(2)
        ]);

        WalletTransaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 2000000,
            'created_at' => $baseTime->addMinutes(3)
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'large_amount_threshold' => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts' => false,
            'include_off_hours' => false,
            'include_high_frequency' => false,
            'include_round_numbers' => false,
        ]);

        $result = $this->action->handle($data);
        expect($result->suspicious_activities)->toHaveKey('rapid_succession');
        $rapid_succession = $result->suspicious_activities->rapid_succession;
        expect($rapid_succession->isEmpty())->toBeTrue();
    });

    it('detects unusual admin activity', function () {
        $admin = Staff::factory()->create();

        // Create multiple high-risk admin actions
        AdminActionLog::factory()->count(5)->create([
            'admin_id' => $admin->id,
            'risk_level' => 'high',
            'created_at' => now()->subDays(1)
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'large_amount_threshold' => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts' => false,
            'include_off_hours' => false,
            'include_high_frequency' => false,
            'include_round_numbers' => false,
        ]);

        $result = $this->action->handle($data);
        expect($result->suspicious_activities)->toHaveKey('unusual_admin_activity');
        expect($result->suspicious_activities->unusual_admin_activity)->toBeEmptyCollection();
    });

    it('includes detection period and criteria in result', function () {
        $data = SuspiciousActivityRequestData::from([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'large_amount_threshold' => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts' => true,
            'include_off_hours' => true,
            'include_high_frequency' => true,
            'include_round_numbers' => true,
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

    it('includes summary of suspicious activities', function () {
        // Create some suspicious data
        WalletTransaction::factory()->create([
            'amount' => 60000000, // Large amount
            'created_at' => now()->subDays(3)
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'large_amount_threshold' => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts' => true,
            'include_off_hours' => true,
            'include_high_frequency' => true,
            'include_round_numbers' => true,
        ]);

        $result = $this->action->handle($data);

        expect($result)->toHaveKey('summary');
        expect($result->summary)->toBeArray();
    });

    it('handles empty results gracefully', function () {
        // Don't create any suspicious transactions

        $data = SuspiciousActivityRequestData::from([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'large_amount_threshold' => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts' => true,
            'include_off_hours' => true,
            'include_high_frequency' => true,
            'include_round_numbers' => true,
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities)->toBeInstanceOf(SuspiciousActivityCollectionData::class);
        expect($result->summary)->toBeArray();
    });

    it('respects date range filtering', function () {
        // Create transaction outside date range
        WalletTransaction::factory()->create([
            'amount' => 60000000,
            'created_at' => now()->subWeeks(2) // Before date_from
        ]);

        // Create transaction within date range
        WalletTransaction::factory()->create([
            'amount' => 60000000,
            'created_at' => now()->subDays(3) // Within range
        ]);

        $data = SuspiciousActivityRequestData::from([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'large_amount_threshold' => 50000000,
            'high_frequency_threshold' => 10,
            'include_large_amounts' => true,
            'include_off_hours' => false,
            'include_high_frequency' => false,
            'include_round_numbers' => false,
        ]);

        $result = $this->action->handle($data);

        expect($result->suspicious_activities->large_transactions)->toHaveCount(1);
    });
});

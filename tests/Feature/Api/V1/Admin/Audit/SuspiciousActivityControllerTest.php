<?php

declare(strict_types=1);

use App\Models\AdminActionLog;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Enums\PermissionEnum;
use App\Enums\Wallet\TransactionTypeEnum;

use Tests\AuthTestTrait;
use Carbon\Carbon;
use \Hekmatinasser\Verta\Facades\Verta;
uses(AuthTestTrait::class);

describe('SuspiciousActivityController', function () {

    beforeEach(function () {
        $this->admin = Staff::factory()->create();
        $this->baseUrl = '/api/v1/admin/audit/suspicious-activity';
        $this->dateFrom = verta()->subMonth()->format('Y-m-d');
        $this->dateTo = verta()->format('Y-m-d');
    });

    it('can detect suspicious activity with proper permissions', function () {
        // Create suspicious transaction (large amount)
        WalletTransaction::factory()->create([
            'amount'     => 60000000, // 60M IRR - above default threshold
            'created_at' => now()->subDays(5)
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                => $this->dateFrom,
                'date_to'                  => $this->dateTo,
                'large_amount_threshold'   => 50000000,
                'high_frequency_threshold' => 10,
                'include_large_amounts'    => true,
                'include_off_hours'        => false,
                'include_high_frequency'   => false,
                'include_round_numbers'    => false,
            ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'detection_period',
                'detection_criteria',
                'suspicious_activities',
                'summary'
            ]
        ]);
    });

    it('requires permission to detect suspicious activity', function () {
        $response = $this->authorized_user([])
            ->postJson($this->baseUrl, [
                'date_from' => $this->dateFrom,
                'date_to'   => $this->dateTo,
            ]);

        $response->assertForbidden();
    });

    it('validates required date_from field', function () {
        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_to' => $this->dateTo,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date_from']);
    });

    it('validates required date_to field', function () {
        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from' => $this->dateFrom,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date_to']);
    });

    it('validates date formats', function () {
        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from' => 'invalid-date',
                'date_to'   => 'invalid-date',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date_from', 'date_to']);
    });

    it('validates that date_from is before date_to', function () {
        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from' => now()->format('Y-m-d'),
                'date_to'   => now()->subDay()->format('Y-m-d'),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date_from']);
    });

    it('validates threshold values are positive integers', function () {
        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                => $this->dateFrom,
                'date_to'                  => $this->dateTo,
                'large_amount_threshold'   => -5000000,
                'high_frequency_threshold' => -10,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['large_amount_threshold', 'high_frequency_threshold']);
    });

    it('detects large amount transactions', function () {
        $largeTransaction = WalletTransaction::factory()->create([
            'amount'     => 60000000, // 60M IRR
            'created_at' => now()->subDays(3)
        ]);

        $normalTransaction = WalletTransaction::factory()->create([
            'amount'     => 1000000, // 1M IRR
            'created_at' => now()->subDays(2)
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'              => $this->dateFrom,
                'date_to'                => $this->dateTo,
                'large_amount_threshold' => 50000000,
                'include_large_amounts'  => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['suspicious_activities'])->toHaveKey('large_transactions');
        expect($data['suspicious_activities']['large_transactions'])->toHaveCount(1);
        expect($data['suspicious_activities']['large_transactions'][0]['transaction_id'])->toBe($largeTransaction->id);
    });

    it('detects off-hours transactions', function () {
        Carbon::setTestNow(Carbon::create(2025, 1, 15, 14, 0, 0)); // Set test time to 2 PM

        $offHoursTransaction = WalletTransaction::factory()->create([
            'amount'     => 5000000,
            'created_at' => Carbon::create(2025, 1, 15, 2, 0, 0) // 2 AM
        ]);

        $normalHoursTransaction = WalletTransaction::factory()->create([
            'amount'     => 5000000,
            'created_at' => Carbon::create(2025, 1, 15, 10, 0, 0) // 10 AM
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'         => verta('2025-1-14')->format('Y-m-d'),
                'date_to'           => verta('2025-1-16')->format('Y-m-d'),
                'include_off_hours' => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['suspicious_activities'])->toHaveKey('off_hours_transactions');
        expect($data['suspicious_activities']['off_hours_transactions'])->toHaveCount(1);
        expect($data['suspicious_activities']['off_hours_transactions'][0]['transaction_id'])->toBe($offHoursTransaction->id);

        Carbon::setTestNow(); // Reset
    });

    it('detects high frequency users', function () {
        $highFreqUser = User::factory()->create();

        // Create 15 transactions for high frequency user
        WalletTransaction::factory()->count(15)->create([
            'user_id'    => $highFreqUser->id,
            'amount'     => 1000000,
            'created_at' => now()->subDays(2)
        ]);

        // Create 5 transactions for normal user
        WalletTransaction::factory()->count(5)->create([
            'amount'     => 1000000,
            'created_at' => now()->subDays(2)
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                => $this->dateFrom,
                'date_to'                  => $this->dateTo,
                'high_frequency_threshold' => 10,
                'include_high_frequency'   => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['suspicious_activities'])->toHaveKey('high_frequency_users');
        expect($data['suspicious_activities']['high_frequency_users'])->toHaveCount(1);
        expect($data['suspicious_activities']['high_frequency_users'][0]['user_id'])->toBe($highFreqUser->id);
        expect($data['suspicious_activities']['high_frequency_users'][0]['transaction_count'])->toBe(15);
    });

    it('detects round number patterns', function () {
        $roundTransaction1 = WalletTransaction::factory()->create([
            'amount'     => 10000000, // 10M - round number
            'created_at' => now()->subDays(3)
        ]);

        $roundTransaction2 = WalletTransaction::factory()->create([
            'amount'     => 5000000, // 5M - round number
            'created_at' => now()->subDays(2)
        ]);

        $nonRoundTransaction = WalletTransaction::factory()->create([
            'amount'     => 1234567, // Not round
            'created_at' => now()->subDays(1)
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'             => $this->dateFrom,
                'date_to'               => $this->dateTo,
                'include_round_numbers' => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['suspicious_activities'])->toHaveKey('round_number_patterns');
        expect($data['suspicious_activities']['round_number_patterns'])->toHaveCount(2);
    });

    it('includes rapid succession detection automatically', function () {
        $user = User::factory()->create();
        $baseTime = now()->subHours(2);

        // Create rapid succession transactions
        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 2000000,
            'created_at' => $baseTime
        ]);

        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 2000000,
            'created_at' => $baseTime->copy()->addMinutes(2)
        ]);

        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 2000000,
            'created_at' => $baseTime->copy()->addMinutes(4)
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from' => $this->dateFrom,
                'date_to'   => $this->dateTo,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['suspicious_activities'])->toHaveKey('rapid_succession');
    });

    it('includes unusual admin activity detection automatically', function () {
        $admin = Staff::factory()->create();

        // Create multiple high-risk admin actions
        AdminActionLog::factory()->count(5)->create([
            'admin_id'   => $admin->id,
            'risk_level' => 'high',
            'created_at' => now()->subDays(1)
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from' => $this->dateFrom,
                'date_to'   => $this->dateTo,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['suspicious_activities'])->toHaveKey('unusual_admin_activity');
    });

    it('includes detection period and criteria in response', function () {
        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                => $this->dateFrom,
                'date_to'                  => $this->dateTo,
                'large_amount_threshold'   => 50000000,
                'high_frequency_threshold' => 10,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['detection_period'])->toHaveKeys(['from', 'to']);
        expect($data['detection_period']['from'])->toContain($this->dateFrom);
        expect($data['detection_period']['to'])->toContain($this->dateTo);

        expect($data['detection_criteria'])->toHaveKeys(['large_amount_threshold', 'high_frequency_threshold']);
        expect($data['detection_criteria']['large_amount_threshold'])->toBe(50000000);
        expect($data['detection_criteria']['high_frequency_threshold'])->toBe(10);
    });

    it('includes summary of suspicious activities', function () {
        // Create some suspicious data
        WalletTransaction::factory()->create([
            'amount'     => 60000000, // Large amount
            'created_at' => now()->subDays(3)
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'              => $this->dateFrom,
                'date_to'                => $this->dateTo,
                'large_amount_threshold' => 50000000,
                'include_large_amounts'  => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data)->toHaveKey('summary');
        expect($data['summary'])->toBeArray();
    });

    it('handles empty results gracefully', function () {
        // Don't create any suspicious transactions

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'              => $this->dateFrom,
                'date_to'                => $this->dateTo,
                'include_large_amounts'  => true,
                'include_off_hours'      => true,
                'include_high_frequency' => true,
                'include_round_numbers'  => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['suspicious_activities'])->toBeArray();
        expect($data['summary'])->toBeArray();
    });

    it('respects date range filtering', function () {
        // Transaction outside range
        $outsideRange = WalletTransaction::factory()->create([
            'amount'     => 60000000,
            'created_at' => Verta::parse($this->dateFrom)->subDay()->toCarbon()->format('Y-m-d')
        ]);

        // Transaction within range
        $withinRange = WalletTransaction::factory()->create([
            'amount'     => 60000000,
            'created_at' => Verta::parse($this->dateFrom)->addDay()->toCarbon()->format('Y-m-d')
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'              => $this->dateFrom,
                'date_to'                => $this->dateTo,
                'large_amount_threshold' => 50000000,
                'include_large_amounts'  => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['suspicious_activities']['large_transactions'])->toHaveCount(1);
        expect($data['suspicious_activities']['large_transactions'][0]['transaction_id'])->toBe($withinRange->id);
    });

    it('uses default thresholds when not provided', function () {
        WalletTransaction::factory()->create([
            'amount'     => 60000000, // Should trigger default threshold
            'created_at' => now()->subDays(3)
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'             => $this->dateFrom,
                'date_to'               => $this->dateTo,
                'include_large_amounts' => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        // Should use default threshold and detect the transaction
        expect($data['suspicious_activities']['large_transactions'])->not->toBeEmpty();
    });

    it('allows customizing thresholds', function () {
        WalletTransaction::factory()->create([
            'amount'     => 30000000, // 30M IRR
            'created_at' => now()->subDays(3)
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'              => $this->dateFrom,
                'date_to'                => $this->dateTo,
                'large_amount_threshold' => 25000000, // Lower threshold
                'include_large_amounts'  => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['suspicious_activities']['large_transactions'])->toHaveCount(1);
        expect($data['detection_criteria']['large_amount_threshold'])->toBe(25000000);
    });

    it('handles boolean flags correctly', function () {
        $response = $this->authorized_user([PermissionEnum::AUDIT_SUSPICIOUS_ACTIVITY_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'              => $this->dateFrom,
                'date_to'                => $this->dateTo,
                'include_large_amounts'  => false,
                'include_off_hours'      => false,
                'include_high_frequency' => false,
                'include_round_numbers'  => false,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        // Specific detection types should not be included
        expect($data['suspicious_activities']['large_transactions'])->toBeEmpty();
        expect($data['suspicious_activities']['off_hours_transactions'])->toBeEmpty();
        expect($data['suspicious_activities']['high_frequency_users'])->toBeEmpty();
        expect($data['suspicious_activities']['round_number_patterns'])->toBeEmpty();

        // But automatic detections should still be included
        expect($data['suspicious_activities'])->toHaveKey('rapid_succession');
        expect($data['suspicious_activities'])->toHaveKey('unusual_admin_activity');
    });
});

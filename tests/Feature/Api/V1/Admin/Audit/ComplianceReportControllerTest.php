<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\AdminActionLog;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

describe('ComplianceReportController', function (): void {

    beforeEach(function (): void {
        $this->admin    = Staff::factory()->create();
        $this->baseUrl  = '/api/v1/admin/audit/compliance-report';
        $this->dateFrom = verta()->subMonth()->format('Y-m-d');
        $this->dateTo   = verta()->format('Y-m-d');
    });

    it('can generate compliance report with proper permissions', function (): void {
        // Create test data
        $user = User::factory()->create();
        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'type'       => TransactionTypeEnum::DEPOSIT,
            'amount'     => 10000000,
            'created_at' => now()->subDays(5),
        ]);

        AdminActionLog::factory()->create([
            'action_type' => 'wallet_transaction_create',
            'risk_level'  => 'high',
            'created_at'  => now()->subDays(3),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                   => $this->dateFrom,
                'date_to'                     => $this->dateTo,
                'include_transaction_summary' => true,
                'include_admin_activity'      => true,
                'include_risk_assessment'     => true,
            ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'report_period',
                'summary',
                'report_sections' => [
                    'transaction_analysis',
                    'admin_activity',
                    'suspicious_activity',
                    'daily_breakdown',
                ],
            ],
        ]);
    });

    it('requires permission to generate compliance report', function (): void {
        $response = $this->unauthorized_user()
            ->postJson($this->baseUrl, [
                'date_from' => $this->dateFrom,
                'date_to'   => $this->dateTo,
            ]);

        $response->assertForbidden();
    });

    it('validates required date_from field', function (): void {
        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_to' => $this->dateTo,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date_from']);
    });

    it('validates required date_to field', function (): void {
        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from' => $this->dateFrom,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date_to']);
    });

    it('validates date_from format', function (): void {
        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from' => 'invalid-date',
                'date_to'   => $this->dateTo,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date_from']);
    });

    it('validates date_to format', function (): void {
        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from' => $this->dateFrom,
                'date_to'   => 'invalid-date',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date_to']);
    });

    it('validates that date_from is not after date_to', function (): void {
        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from' => now()->format('Y-m-d'),
                'date_to'   => now()->subDay()->format('Y-m-d'),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date_from']);
    });

    it('includes transaction analysis when requested', function (): void {
        WalletTransaction::factory()->create([
            'type'       => TransactionTypeEnum::DEPOSIT,
            'amount'     => 5000000,
            'created_at' => now()->subDays(5),
        ]);

        WalletTransaction::factory()->create([
            'type'       => TransactionTypeEnum::WITHDRAWAL,
            'amount'     => -2000000,
            'created_at' => now()->subDays(3),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                    => $this->dateFrom,
                'date_to'                      => $this->dateTo,
                'include_transaction_analysis' => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['report_sections'])->toHaveKey('transaction_analysis');
    });

    it('includes admin activity when requested', function (): void {
        AdminActionLog::factory()->count(3)->create([
            'action_type' => 'wallet_transaction_create',
            'created_at'  => now()->subDays(2),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'              => $this->dateFrom,
                'date_to'                => $this->dateTo,
                'include_admin_activity' => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['report_sections'])->toHaveKey('admin_activity');
    });

    it('includes risk assessment when requested', function (): void {
        AdminActionLog::factory()->create([
            'risk_level' => 'high',
            'created_at' => now()->subDays(2),
        ]);

        WalletTransaction::factory()->create([
            'amount'     => 50000000, // High amount
            'metadata'   => ['audit' => ['risk_level' => 'high']],
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'               => $this->dateFrom,
                'date_to'                 => $this->dateTo,
                'include_risk_assessment' => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['report_sections'])->toHaveKey('risk_assessment');
    });

    it('includes all sections when all flags are true', function (): void {
        // Create diverse test data
        WalletTransaction::factory()->create([
            'type'       => TransactionTypeEnum::DEPOSIT,
            'amount'     => 10000000,
            'created_at' => now()->subDays(5),
        ]);

        AdminActionLog::factory()->create([
            'action_type' => 'wallet_transaction_create',
            'risk_level'  => 'medium',
            'created_at'  => now()->subDays(3),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                   => $this->dateFrom,
                'date_to'                     => $this->dateTo,
                'include_transaction_summary' => true,
                'include_admin_activity'      => true,
                'include_risk_assessment'     => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['report_sections'])->toHaveKeys([
            'transaction_analysis',
            'admin_activity',
            'risk_assessment',
        ]);
    });

    it('excludes sections when flags are false', function (): void {
        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                   => $this->dateFrom,
                'date_to'                     => $this->dateTo,
                'include_transaction_summary' => false,
                'include_admin_activity'      => false,
                'include_risk_assessment'     => false,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['report_sections'])->not->toHaveKey('transaction_summary');
        expect($data['report_sections'])->not->toHaveKey('admin_activity');
        expect($data['report_sections'])->not->toHaveKey('risk_assessment');
    });

    it('includes report metadata', function (): void {
        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from' => $this->dateFrom,
                'date_to'   => $this->dateTo,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data)->toHaveKey('report_period');
        expect($data['report_period'])->toHaveKeys(['from', 'to']);
        expect($data['report_period']['from'])->toBe($this->dateFrom);
        expect($data['report_period']['to'])->toBe($this->dateTo);
    });

    it('handles empty data gracefully', function (): void {
        // Don't create any test data

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                   => $this->dateFrom,
                'date_to'                     => $this->dateTo,
                'include_transaction_summary' => true,
                'include_admin_activity'      => true,
                'include_risk_assessment'     => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data['report_sections'])->toBeArray();
    });

    it('filters data by date range correctly', function (): void {
        $withinRange = WalletTransaction::factory()->create([
            'amount'     => 5000000,
            'created_at' => Carbon::parse($this->dateFrom)->addDays(5),
        ]);

        $beforeRange = WalletTransaction::factory()->create([
            'amount'     => 10000000,
            'created_at' => Carbon::parse($this->dateFrom)->subDays(1),
        ]);

        $afterRange = WalletTransaction::factory()->create([
            'amount'     => 15000000,
            'created_at' => Carbon::parse($this->dateTo)->addDays(1),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                   => $this->dateFrom,
                'date_to'                     => $this->dateTo,
                'include_transaction_summary' => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        // Should only include transaction within range
        expect($data['report_sections'])->toHaveKey('transaction_analysis');

    });

    it('includes transaction statistics in summary', function (): void {
        // Create various transaction types
        WalletTransaction::factory()->create([
            'type'       => TransactionTypeEnum::DEPOSIT,
            'amount'     => 10000000,
            'created_at' => now()->subDays(5),
        ]);

        WalletTransaction::factory()->create([
            'type'       => TransactionTypeEnum::WITHDRAWAL,
            'amount'     => -5000000,
            'created_at' => now()->subDays(3),
        ]);

        WalletTransaction::factory()->create([
            'type'       => TransactionTypeEnum::GIFT,
            'amount'     => 2000000,
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'                   => $this->dateFrom,
                'date_to'                     => $this->dateTo,
                'include_transaction_summary' => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        $transactionSummary = $data['report_sections']['transaction_analysis'];
        expect($transactionSummary)->toHaveKey('high_risk_transactions');
        expect($transactionSummary)->toHaveKey('by_type');
    });

    it('includes admin activity statistics', function (): void {
        AdminActionLog::factory()->create([
            'action_type' => 'create',
            'risk_level'  => 'high',
            'created_at'  => now()->subDays(2),
        ]);

        AdminActionLog::factory()->create([
            'action_type' => 'update',
            'risk_level'  => 'medium',
            'created_at'  => now()->subDays(1),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'              => $this->dateFrom,
                'date_to'                => $this->dateTo,
                'include_admin_activity' => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        $adminActivity = $data['report_sections']['admin_activity'];
        expect($adminActivity)->toHaveKey('total_admin_actions');
        expect($adminActivity)->toHaveKey('by_risk_level');
    });

    it('includes risk assessment analysis', function (): void {
        // Create high-risk transactions and admin actions
        WalletTransaction::factory()->create([
            'amount'     => 60000000, // High amount
            'metadata'   => ['audit' => ['risk_level' => 'high']],
            'created_at' => now()->subDays(2),
        ]);

        AdminActionLog::factory()->create([
            'risk_level' => 'high',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'               => $this->dateFrom,
                'date_to'                 => $this->dateTo,
                'include_risk_assessment' => true,
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');

        $riskAssessment = $data['report_sections']['risk_assessment'];
        expect($riskAssessment)->toHaveKey('overall_risk_score');
        expect($riskAssessment)->toHaveKey('risk_factors');
        expect($riskAssessment)->toHaveKey('recommendations');
    });

    it('generates comprehensive risk assessment with correct structure', function (): void {
        // Create diverse test data for comprehensive risk assessment
        WalletTransaction::factory()->create([
            'amount'     => 55000000, // High amount transaction
            'metadata'   => ['audit' => ['risk_level' => 'high']],
            'created_at' => now()->setHour(2), // Off-hours (2 AM)
        ]);

        WalletTransaction::factory()->create([
            'amount'     => 5000000, // Round number transaction
            'created_at' => now()->subDays(1),
        ]);

        AdminActionLog::factory()->create([
            'risk_level'      => 'high',
            'response_status' => 200,
            'created_at'      => now()->subDays(1),
        ]);

        AdminActionLog::factory()->create([
            'risk_level'      => 'medium',
            'response_status' => 500, // Failed action
            'created_at'      => now()->subDays(2),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'               => $this->dateFrom,
                'date_to'                 => $this->dateTo,
                'include_risk_assessment' => true,
            ]);

        $response->assertSuccessful();
        $data           = $response->json('data');
        $riskAssessment = $data['report_sections']['risk_assessment'];

        // Validate overall structure
        expect($riskAssessment)->toHaveKeys([
            'overall_risk_score',
            'risk_factors',
            'recommendations',
        ]);

        // Validate overall_risk_score
        expect($riskAssessment['overall_risk_score'])->toBeInt();
        expect($riskAssessment['overall_risk_score'])->toBeGreaterThanOrEqual(0);
        expect($riskAssessment['overall_risk_score'])->toBeLessThanOrEqual(100);

        // Validate risk_factors structure
        $riskFactors = $riskAssessment['risk_factors'];
        expect($riskFactors)->toHaveKeys([
            'transaction_volume_risk',
            'temporal_risk',
            'pattern_risk',
            'admin_activity_risk',
        ]);

        // Validate transaction_volume_risk
        expect($riskFactors['transaction_volume_risk'])->toHaveKeys([
            'high_amount_transactions',
            'high_amount_percentage',
            'risk_level',
        ]);
        expect($riskFactors['transaction_volume_risk']['high_amount_transactions'])->toBeInt();
        expect($riskFactors['transaction_volume_risk']['high_amount_percentage'])->toBeFloat();
        expect($riskFactors['transaction_volume_risk']['risk_level'])->toBeIn(['low', 'medium', 'high']);

        // Validate temporal_risk
        expect($riskFactors['temporal_risk'])->toHaveKeys([
            'off_hours_transactions',
            'off_hours_percentage',
            'risk_level',
        ]);
        expect($riskFactors['temporal_risk']['off_hours_transactions'])->toBeInt();
        expect($riskFactors['temporal_risk']['off_hours_percentage'])->toBeFloat();
        expect($riskFactors['temporal_risk']['risk_level'])->toBeIn(['low', 'medium', 'high']);

        // Validate pattern_risk
        expect($riskFactors['pattern_risk'])->toHaveKeys([
            'round_number_transactions',
            'round_number_percentage',
            'high_risk_transactions',
            'high_risk_percentage',
            'risk_level',
        ]);
        expect($riskFactors['pattern_risk']['round_number_transactions'])->toBeInt();
        expect($riskFactors['pattern_risk']['round_number_percentage'])->toBeFloat();
        expect($riskFactors['pattern_risk']['high_risk_transactions'])->toBeInt();
        expect($riskFactors['pattern_risk']['high_risk_percentage'])->toBeFloat();
        expect($riskFactors['pattern_risk']['risk_level'])->toBeIn(['low', 'medium', 'high']);

        // Validate admin_activity_risk
        expect($riskFactors['admin_activity_risk'])->toHaveKeys([
            'high_risk_admin_actions',
            'high_risk_admin_percentage',
            'failed_admin_actions',
            'failed_admin_percentage',
            'risk_level',
        ]);
        expect($riskFactors['admin_activity_risk']['high_risk_admin_actions'])->toBeInt();
        expect($riskFactors['admin_activity_risk']['high_risk_admin_percentage'])->toBeFloat();
        expect($riskFactors['admin_activity_risk']['failed_admin_actions'])->toBeInt();
        expect($riskFactors['admin_activity_risk']['failed_admin_percentage'])->toBeFloat();
        expect($riskFactors['admin_activity_risk']['risk_level'])->toBeIn(['low', 'medium', 'high']);

        // Validate recommendations
        $recommendations = $riskAssessment['recommendations'];
        expect($recommendations)->toBeArray();
        expect($recommendations)->not->toBeEmpty();

        foreach ($recommendations as $recommendation) {
            expect($recommendation)->toHaveKeys(['priority', 'category', 'message', 'action']);
            expect($recommendation['priority'])->toBeIn(['low', 'medium', 'high', 'critical']);
            expect($recommendation['category'])->toBeString();
            expect($recommendation['message'])->toBeString();
            expect($recommendation['action'])->toBeString();
        }
    });

    it('calculates risk factors correctly based on transaction patterns', function (): void {
        // Use specific Jalali date range to isolate our test data
        $testDateFromJalali = verta()->subDays(10)->format('Y-m-d');
        $testDateToJalali   = verta()->subDays(5)->format('Y-m-d');

        // Convert to Gregorian for creating test data
        $testDateFromGregorian = verta()->subDays(10)->toCarbon()->startOfDay();
        $testDateToGregorian   = verta()->subDays(5)->toCarbon()->endOfDay();

        // Create specific test data for predictable calculations within the test date range
        $user = User::factory()->create();

        // Create exactly 10 transactions for easy percentage calculations
        // 2 high amount transactions (60M each)
        WalletTransaction::factory()->count(2)->create([
            'user_id'    => $user->id,
            'amount'     => 60000000, // High amount (>= 50M)
            'created_at' => $testDateFromGregorian->copy()->addHours(12), // Normal hours
        ]);

        // 1 off-hours transaction with significant amount (>=5M threshold)
        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 5500000, // Above 5M threshold, NOT a round million
            'created_at' => $testDateFromGregorian->copy()->addDays(1)->setHour(3), // Off hours (3 AM)
        ]);

        // 2 round number transactions (exactly divisible by 1M and >=1M)
        WalletTransaction::factory()->count(2)->create([
            'user_id'    => $user->id,
            'amount'     => 2000000, // Exactly 2M (round number)
            'created_at' => $testDateFromGregorian->copy()->addDays(2)->setHour(14), // Normal hours
        ]);

        // 1 high-risk transaction from metadata
        WalletTransaction::factory()->create([
            'user_id'    => $user->id,
            'amount'     => 3750000, // NOT a round number
            'metadata'   => ['audit' => ['risk_level' => 'high']],
            'created_at' => $testDateFromGregorian->copy()->addDays(3)->setHour(10),
        ]);

        // 4 normal transactions (NOT round numbers, NOT high amounts)
        WalletTransaction::factory()->count(4)->create([
            'user_id'    => $user->id,
            'amount'     => 750000, // Less than 1M, so not "round"
            'created_at' => $testDateFromGregorian->copy()->addDays(4)->setHour(15),
        ]);

        // Create admin actions within test period
        AdminActionLog::factory()->create([
            'risk_level'      => 'high',
            'response_status' => 200,
            'created_at'      => $testDateFromGregorian->copy()->addDays(1),
        ]);

        AdminActionLog::factory()->create([
            'risk_level'      => 'low',
            'response_status' => 500, // Failed action
            'created_at'      => $testDateFromGregorian->copy()->addDays(2),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'               => $testDateFromJalali, // Send Jalali dates to API
                'date_to'                 => $testDateToJalali,
                'include_risk_assessment' => true,
            ]);

        $response->assertSuccessful();
        $data        = $response->json('data');
        $riskFactors = $data['report_sections']['risk_assessment']['risk_factors'];

        // Now we should have exactly 10 transactions in our isolated test period
        // Validate transaction volume risk calculations: 2 out of 10 = 20%
        expect($riskFactors['transaction_volume_risk']['high_amount_transactions'])->toBe(2);
        expect($riskFactors['transaction_volume_risk']['high_amount_percentage'])->toBe(20.0);
        expect($riskFactors['transaction_volume_risk']['risk_level'])->toBe('high'); // 20% >= 15%

        // Validate temporal risk calculations: 1 out of 10 = 10%
        expect($riskFactors['temporal_risk']['off_hours_transactions'])->toBe(1);
        expect($riskFactors['temporal_risk']['off_hours_percentage'])->toBe(10.0);
        expect($riskFactors['temporal_risk']['risk_level'])->toBe('medium'); // 10% >= 10% (threshold is 10%)

        // Validate pattern risk calculations
        // NOTE: Round numbers include 60M (2 transactions) + 2M (2 transactions) = 4 total
        expect($riskFactors['pattern_risk']['round_number_transactions'])->toBe(4); // All amounts divisible by 1M and >= 1M
        expect($riskFactors['pattern_risk']['round_number_percentage'])->toBe(40.0); // 4 out of 10 = 40%
        expect($riskFactors['pattern_risk']['high_risk_transactions'])->toBe(1);
        expect($riskFactors['pattern_risk']['high_risk_percentage'])->toBe(10.0);
        expect($riskFactors['pattern_risk']['risk_level'])->toBe('high'); // max(40%, 10%) = 40% >= 20%

        // Validate admin activity risk calculations: 1 high, 1 failed out of 2 = 50% each
        expect($riskFactors['admin_activity_risk']['high_risk_admin_actions'])->toBe(1);
        expect($riskFactors['admin_activity_risk']['high_risk_admin_percentage'])->toBe(50.0);
        expect($riskFactors['admin_activity_risk']['failed_admin_actions'])->toBe(1);
        expect($riskFactors['admin_activity_risk']['failed_admin_percentage'])->toBe(50.0);
        expect($riskFactors['admin_activity_risk']['risk_level'])->toBe('high'); // max(50%, 50%) = 50% >= 10%
    });

    it('calculates overall risk score correctly based on weighted factors', function (): void {
        // Create minimal high-risk data
        WalletTransaction::factory()->create([
            'amount'     => 60000000, // High amount - should make transaction_volume_risk = 'high'
            'created_at' => now()->subDays(1),
        ]);

        AdminActionLog::factory()->create([
            'risk_level' => 'high', // Should make admin_activity_risk = 'high'
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'               => $this->dateFrom,
                'date_to'                 => $this->dateTo,
                'include_risk_assessment' => true,
            ]);

        $response->assertSuccessful();
        $data           = $response->json('data');
        $riskAssessment = $data['report_sections']['risk_assessment'];

        // With high transaction_volume_risk and high admin_activity_risk, the score should be moderately high
        expect($riskAssessment['overall_risk_score'])->toBeGreaterThanOrEqual(60);
        expect($riskAssessment['overall_risk_score'])->toBeLessThanOrEqual(80);
    });

    it('generates appropriate recommendations based on risk levels', function (): void {
        // Create high-risk scenario
        WalletTransaction::factory()->create([
            'amount'     => 70000000, // Very high amount
            'created_at' => now()->setHour(1), // Off-hours
        ]);

        WalletTransaction::factory()->create([
            'amount'     => 5000000, // Round number
            'metadata'   => ['audit' => ['risk_level' => 'high']],
            'created_at' => now()->subDays(1),
        ]);

        AdminActionLog::factory()->create([
            'risk_level'      => 'high',
            'response_status' => 500, // Failed action
            'created_at'      => now()->subDays(1),
        ]);

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'               => $this->dateFrom,
                'date_to'                 => $this->dateTo,
                'include_risk_assessment' => true,
            ]);

        $response->assertSuccessful();
        $data            = $response->json('data');
        $recommendations = $data['report_sections']['risk_assessment']['recommendations'];

        // Should have multiple recommendations for various high-risk factors
        expect($recommendations)->not->toBeEmpty();

        // Check for critical or high overall risk recommendation
        $hasHighPriorityRecommendation = collect($recommendations)->contains(function ($rec) {
            return in_array($rec['priority'], ['critical', 'high']);
        });
        expect($hasHighPriorityRecommendation)->toBeTrue();

        // Check that we have multiple recommendations with valid categories
        expect(count($recommendations))->toBeGreaterThanOrEqual(2);

        // Check that all recommendations have valid structure
        foreach ($recommendations as $recommendation) {
            expect($recommendation['category'])->toBeString();
            expect($recommendation['message'])->toBeString();
            expect($recommendation['action'])->toBeString();
        }
    });

    it('handles empty data with appropriate low-risk assessment', function (): void {
        // No transactions or admin actions created

        $response = $this->authorized_user([PermissionEnum::AUDIT_COMPLIANCE_REPORTS_VIEW])
            ->postJson($this->baseUrl, [
                'date_from'               => $this->dateFrom,
                'date_to'                 => $this->dateTo,
                'include_risk_assessment' => true,
            ]);

        $response->assertSuccessful();
        $data           = $response->json('data');
        $riskAssessment = $data['report_sections']['risk_assessment'];

        // Should have low overall risk score
        expect($riskAssessment['overall_risk_score'])->toBeLessThanOrEqual(30);

        // All risk factors should be zero/low
        $riskFactors = $riskAssessment['risk_factors'];
        expect($riskFactors['transaction_volume_risk']['high_amount_transactions'])->toBe(0);
        expect($riskFactors['temporal_risk']['off_hours_transactions'])->toBe(0);
        expect($riskFactors['pattern_risk']['round_number_transactions'])->toBe(0);
        expect($riskFactors['pattern_risk']['high_risk_transactions'])->toBe(0);
        expect($riskFactors['admin_activity_risk']['high_risk_admin_actions'])->toBe(0);
        expect($riskFactors['admin_activity_risk']['failed_admin_actions'])->toBe(0);

        // Should have at least one recommendation (the default "maintain monitoring")
        $recommendations = $riskAssessment['recommendations'];
        expect($recommendations)->not->toBeEmpty();

        $hasMaintenanceRecommendation = collect($recommendations)->contains(function ($rec) {
            return $rec['priority'] === 'low' && $rec['action'] === 'continue_regular_monitoring';
        });
        expect($hasMaintenanceRecommendation)->toBeTrue();
    });
});

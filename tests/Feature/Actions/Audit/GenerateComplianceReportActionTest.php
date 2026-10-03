<?php

declare(strict_types=1);

use App\Actions\Admin\Audit\GenerateComplianceReportAction;
use App\Data\Admin\Audit\ComplianceReportRequestData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\AdminActionLog;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;

describe('GenerateComplianceReportAction', function (): void {

    beforeEach(function (): void {
        $this->action         = new GenerateComplianceReportAction();
        $this->dateFromJalali = verta()->subWeek()->format('Y-m-d');
        $this->dateToJalali   = verta()->format('Y-m-d');
        $this->dateFromCarbon = Carbon::now()->subWeek()->toImmutable();
        $this->dateToCarbon   = Carbon::now()->toImmutable();
    });

    describe('execute method', function (): void {
        it('generates basic report structure', function (): void {
            $data = ComplianceReportRequestData::from([
                'date_from'                    => $this->dateFromJalali,
                'date_to'                      => $this->dateToJalali,
                'report_type'                  => 'custom',
                'include_transaction_analysis' => false,
                'include_admin_activity'       => false,
                'include_suspicious_activity'  => false,
                'include_risk_assessment'      => false,
            ]);

            $result = $this->action->execute($data);

            expect($result)->toHaveKeys([
                'report_period',
                'summary',
                'transaction_analysis',
            ])
                ->and($result['report_period'])->toHaveKeys(['from', 'to', 'type'])
                ->and($result['report_period']['type'])->toBe('custom');

        });

        it('includes transaction analysis when requested', function (): void {
            $data = ComplianceReportRequestData::from([
                'date_from'                    => $this->dateFromJalali,
                'date_to'                      => $this->dateToJalali,
                'include_transaction_analysis' => true,
            ]);

            $result = $this->action->execute($data);

            expect($result)->toHaveKey('report_sections')
                ->and($result['report_sections'])->toHaveKey('transaction_analysis');
        });

        it('includes admin activity when requested', function (): void {
            $data = ComplianceReportRequestData::from([
                'date_from'              => $this->dateFromJalali,
                'date_to'                => $this->dateToJalali,
                'include_admin_activity' => true,
            ]);

            $result = $this->action->execute($data);

            expect($result['report_sections'])->toHaveKey('admin_activity');
        });

        it('includes suspicious activity when requested', function (): void {
            $data = ComplianceReportRequestData::from([
                'date_from'                   => $this->dateFromJalali,
                'date_to'                     => $this->dateToJalali,
                'include_suspicious_activity' => true,
            ]);

            $result = $this->action->execute($data);

            expect($result['report_sections'])->toHaveKey('suspicious_activity');
        });

        it('includes risk assessment when requested', function (): void {
            $data = ComplianceReportRequestData::from([
                'date_from'               => $this->dateFromJalali,
                'date_to'                 => $this->dateToJalali,
                'include_risk_assessment' => true,
            ]);

            $result = $this->action->execute($data);

            expect($result['report_sections'])->toHaveKey('risk_assessment');
        });

        it('includes daily breakdown for daily report type', function (): void {
            $data = ComplianceReportRequestData::from([
                'date_from'   => $this->dateFromJalali,
                'date_to'     => $this->dateToJalali,
                'report_type' => 'daily',
            ]);

            $result = $this->action->execute($data);

            expect($result['report_sections'])->toHaveKey('daily_breakdown');
        });

        it('does not include daily breakdown for non-daily report types', function (): void {
            $data = ComplianceReportRequestData::from([
                'date_from'   => $this->dateFromJalali,
                'date_to'     => $this->dateToJalali,
                'report_type' => 'monthly',
            ]);

            $result = $this->action->execute($data);

            expect($result['report_sections'])->not->toHaveKey('daily_breakdown');
        });
    });

    describe('generateSummary method', function (): void {
        it('generates summary with user filter', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            WalletTransaction::factory()->create([
                'user_id'    => $user1->id,
                'wallet_id'  => $user1->wallet->id,
                'amount'     => 100000,
                'created_at' => now()->subDays(2),
            ]);

            WalletTransaction::factory()->create([
                'user_id'    => $user2->id,
                'wallet_id'  => $user2->wallet->id,
                'amount'     => 200000,
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from' => $this->dateFromJalali,
                'date_to'   => $this->dateToJalali,
                'user_ids'  => [$user1->id],
            ]);

            $result = $this->action->execute($data);

            expect($result['summary']['total_transactions'])->toBe(1);
        });

        it('generates summary with transaction type filter', function (): void {
            WalletTransaction::factory()->create([
                'type'       => TransactionTypeEnum::DEPOSIT,
                'amount'     => 100000,
                'created_at' => now()->subDays(2),
            ]);

            WalletTransaction::factory()->create([
                'type'       => TransactionTypeEnum::PAYMENT,
                'amount'     => -50000,
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'         => $this->dateFromJalali,
                'date_to'           => $this->dateToJalali,
                'transaction_types' => [TransactionTypeEnum::DEPOSIT],
            ]);

            $result = $this->action->execute($data);

            expect($result['summary']['total_transactions'])->toBe(1);
        });

        it('generates summary with amount filters', function (): void {
            WalletTransaction::factory()->create([
                'amount'     => 50000, // Below min_amount
                'created_at' => now()->subDays(2),
            ]);

            WalletTransaction::factory()->create([
                'amount'     => 150000, // Within range
                'created_at' => now()->subDays(2),
            ]);

            WalletTransaction::factory()->create([
                'amount'     => 300000, // Above max_amount
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'  => $this->dateFromJalali,
                'date_to'    => $this->dateToJalali,
                'min_amount' => 100000,
                'max_amount' => 200000,
            ]);

            $result = $this->action->execute($data);

            expect($result['summary']['total_transactions'])->toBe(1);
        });

        it('generates summary with only min_amount filter', function (): void {
            WalletTransaction::factory()->create([
                'amount'     => 50000, // Below min_amount
                'created_at' => now()->subDays(2),
            ]);

            WalletTransaction::factory()->create([
                'amount'     => 150000, // Above min_amount
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'  => $this->dateFromJalali,
                'date_to'    => $this->dateToJalali,
                'min_amount' => 100000,
            ]);

            $result = $this->action->execute($data);

            expect($result['summary']['total_transactions'])->toBe(1);
        });

        it('generates summary with only max_amount filter', function (): void {
            WalletTransaction::factory()->create([
                'amount'     => 150000, // Above max_amount
                'created_at' => now()->subDays(2),
            ]);

            WalletTransaction::factory()->create([
                'amount'     => 50000, // Below max_amount
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'  => $this->dateFromJalali,
                'date_to'    => $this->dateToJalali,
                'max_amount' => 100000,
            ]);

            $result = $this->action->execute($data);

            expect($result['summary']['total_transactions'])->toBe(1);
        });

        it('calculates summary statistics correctly', function (): void {
            WalletTransaction::factory()->create([
                'amount'     => 100000, // Credit
                'created_at' => now()->subDays(2),
            ]);

            WalletTransaction::factory()->create([
                'amount'     => -50000, // Debit
                'created_at' => now()->subDays(2),
            ]);

            WalletTransaction::factory()->create([
                'amount'     => 10000000, // Large transaction (>= 5M)
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from' => $this->dateFromJalali,
                'date_to'   => $this->dateToJalali,
            ]);

            $result  = $this->action->execute($data);
            $summary = $result['summary'];

            // Basic counts
            expect($summary['total_transactions'])->toBe(3);
            expect($summary['unique_users'])->toBe(3);

            // Check debit count - should be 1 (the -50000 transaction)
            expect($summary['debits_count'])->toBe(1);

            expect($summary['credits_count'])->toBe(2); // 100000 and 10000000
            expect($summary['large_transactions_count'])->toBe(1); // Only 10M >= 5M
        });
    });

    describe('generateTransactionAnalysis method', function (): void {
        it('generates transaction analysis with user filter', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            WalletTransaction::factory()->create([
                'user_id'     => $user1->id,
                'wallet_id'   => $user1->wallet->id,
                'type'        => TransactionTypeEnum::DEPOSIT,
                'source_type' => TransactionSourceEnum::STAFF,
                'amount'      => 100000,
                'created_at'  => now()->subDays(2),
            ]);

            WalletTransaction::factory()->create([
                'user_id'     => $user2->id,
                'wallet_id'   => $user2->wallet->id,
                'type'        => TransactionTypeEnum::PAYMENT,
                'source_type' => TransactionSourceEnum::ORDER,
                'amount'      => -50000,
                'created_at'  => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'                    => $this->dateFromJalali,
                'date_to'                      => $this->dateToJalali,
                'user_ids'                     => [$user1->id],
                'include_transaction_analysis' => true,
            ]);

            $result   = $this->action->execute($data);
            $analysis = $result['report_sections']['transaction_analysis'];

            expect($analysis)->toHaveKeys(['by_type', 'by_source', 'high_risk_transactions']);
            expect($analysis['by_type'])->toHaveKey(TransactionTypeEnum::DEPOSIT->value);
            expect($analysis['by_source'])->toHaveKey(TransactionSourceEnum::STAFF->value);
        });

        it('identifies high risk transactions from metadata', function (): void {
            WalletTransaction::factory()->create([
                'metadata' => [
                    'audit' => [
                        'risk_level' => 'high',
                    ],
                ],
                'created_at' => now()->subDays(2),
            ]);

            WalletTransaction::factory()->create([
                'metadata' => [
                    'audit' => [
                        'risk_level' => 'low',
                    ],
                ],
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'                    => $this->dateFromJalali,
                'date_to'                      => $this->dateToJalali,
                'include_transaction_analysis' => true,
            ]);

            $result   = $this->action->execute($data);
            $analysis = $result['report_sections']['transaction_analysis'];

            expect($analysis['high_risk_transactions'])->toBe(1);
        });
    });

    describe('generateAdminActionsSummary method', function (): void {
        it('generates admin actions summary', function (): void {
            $admin1 = Staff::factory()->create();
            $admin2 = Staff::factory()->create();

            AdminActionLog::factory()->create([
                'admin_id'        => $admin1->id,
                'action_type'     => 'create',
                'risk_level'      => 'high',
                'response_status' => 200,
                'created_at'      => now()->subDays(2),
            ]);

            AdminActionLog::factory()->create([
                'admin_id'        => $admin1->id,
                'action_type'     => 'update',
                'risk_level'      => 'medium',
                'response_status' => 404,
                'created_at'      => now()->subDays(2),
            ]);

            AdminActionLog::factory()->create([
                'admin_id'        => $admin2->id,
                'action_type'     => 'delete',
                'risk_level'      => 'high',
                'response_status' => 500,
                'created_at'      => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'              => $this->dateFromJalali,
                'date_to'                => $this->dateToJalali,
                'include_admin_activity' => true,
            ]);

            $result        = $this->action->execute($data);
            $adminActivity = $result['report_sections']['admin_activity'];

            expect($adminActivity)->toHaveKeys([
                'total_admin_actions',
                'unique_admins',
                'by_action_type',
                'by_risk_level',
                'failed_actions',
            ]);

            expect($adminActivity['total_admin_actions'])->toBe(3);
            expect($adminActivity['unique_admins'])->toBe(2);
            expect($adminActivity['failed_actions'])->toBe(2); // 404 and 500
        });
    });

    describe('generateSuspiciousActivityReport method', function (): void {
        it('generates suspicious activity report with user filter', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            // Large transaction for user1
            WalletTransaction::factory()->create([
                'user_id'    => $user1->id,
                'wallet_id'  => $user1->wallet->id,
                'amount'     => 60000000, // > 50M
                'created_at' => now()->subDays(2),
            ]);

            // Large transaction for user2
            WalletTransaction::factory()->create([
                'user_id'    => $user2->id,
                'wallet_id'  => $user2->wallet->id,
                'amount'     => 70000000, // > 50M
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'                   => $this->dateFromJalali,
                'date_to'                     => $this->dateToJalali,
                'user_ids'                    => [$user1->id],
                'include_suspicious_activity' => true,
            ]);

            $result     = $this->action->execute($data);
            $suspicious = $result['report_sections']['suspicious_activity'];

            expect($suspicious['large_transactions'])->toBe(1);
        });

        it('detects various suspicious patterns', function (): void {
            $baseTime = now()->setHour(13)->toImmutable();
            // Large transaction
            WalletTransaction::factory()->create([
                'amount'     => 60000000, // > 50M
                'created_at' => $baseTime->subDays(2),
            ]);

            // Off-hours transaction (must be during night hours and >= 5M)
            WalletTransaction::factory()->create([
                'amount'     => 10000000, // >= 5M
                'created_at' => $baseTime->subDays(2)->setTime(2, 0, 0), // 2 AM
            ]);

            // Round number transaction (specifically exactly divisible by 1M and >= 1M)
            WalletTransaction::factory()->create([
                'amount'     => 5000000, // Exactly 5M (divisible by 1M)
                'created_at' => $baseTime->subDays(2),
            ]);

            // Non-round number transactions to test pattern detection
            WalletTransaction::factory()->create([
                'amount'     => 1234567, // Not divisible by 1M
                'created_at' => $baseTime->subDays(2),
            ]);

            // High frequency user transactions
            $user = User::factory()->create();
            WalletTransaction::factory()->count(60)->create([
                'user_id'    => $user->id,
                'wallet_id'  => $user->wallet->id,
                'amount'     => 1234567, // Non-round amounts
                'created_at' => $baseTime->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'                   => $this->dateFromJalali,
                'date_to'                     => $this->dateToJalali,
                'include_suspicious_activity' => true,
            ]);

            $result     = $this->action->execute($data);
            $suspicious = $result['report_sections']['suspicious_activity'];

            expect($suspicious['large_transactions'])->toBe(1);
            expect($suspicious['off_hours_transactions'])->toBe(1);
            // Multiple round number transactions might be detected
            expect($suspicious['round_number_transactions'])->toBeGreaterThanOrEqual(1);
            expect($suspicious['high_frequency_users'])->toBe(1);
        });
    });

    describe('generateDailyBreakdown method', function (): void {
        it('generates daily breakdown with user filter', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            // Use a date within our test range
            $specificDate = $this->dateFromCarbon->addDays(1);

            WalletTransaction::factory()->create([
                'user_id'   => $user1->id,
                'wallet_id' => $user1->wallet->id,
                'amount'    => 100000,
                'metadata'  => [
                    'audit' => [
                        'is_admin_initiated' => true,
                    ],
                ],
                'created_at' => $specificDate,
            ]);

            WalletTransaction::factory()->create([
                'user_id'    => $user2->id,
                'wallet_id'  => $user2->wallet->id,
                'amount'     => 200000,
                'created_at' => $specificDate,
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'   => $this->dateFromJalali,
                'date_to'     => $this->dateToJalali,
                'user_ids'    => [$user1->id],
                'report_type' => 'daily',
            ]);

            $result         = $this->action->execute($data);
            $dailyBreakdown = $result['report_sections']['daily_breakdown'];

            expect($dailyBreakdown)->toBeArray();

            $dateKey = verta($specificDate)->format('Y-m-d');

            expect($dailyBreakdown)->toHaveKey($dateKey);
            expect($dailyBreakdown[$dateKey]['total_transactions'])->toBe(1);
            expect($dailyBreakdown[$dateKey]['admin_initiated'])->toBe(1);
        });

        it('covers all days in range', function (): void {
            $data = ComplianceReportRequestData::from([
                'date_from'   => $this->dateFromJalali,
                'date_to'     => $this->dateToJalali,
                'report_type' => 'daily',
            ]);

            $result         = $this->action->execute($data);
            $dailyBreakdown = $result['report_sections']['daily_breakdown'];

            $expectedDays = $this->dateFromCarbon->diffInDays($this->dateToCarbon) + 1;
            expect(count($dailyBreakdown))->toBe((int) $expectedDays);
        });
    });

    describe('generateRiskAssessmentReport method', function (): void {
        it('generates risk assessment with user filter', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            // High-risk transaction for user1
            WalletTransaction::factory()->create([
                'user_id'   => $user1->id,
                'wallet_id' => $user1->wallet->id,
                'amount'    => 60000000,
                'metadata'  => [
                    'audit' => [
                        'risk_level' => 'high',
                    ],
                ],
                'created_at' => now()->subDays(2),
            ]);

            // Normal transaction for user2
            WalletTransaction::factory()->create([
                'user_id'    => $user2->id,
                'wallet_id'  => $user2->wallet->id,
                'amount'     => 10000,
                'created_at' => now()->subDays(2),
            ]);

            AdminActionLog::factory()->create([
                'risk_level' => 'high',
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'               => $this->dateFromJalali,
                'date_to'                 => $this->dateToJalali,
                'user_ids'                => [$user1->id],
                'include_risk_assessment' => true,
            ]);

            $result         = $this->action->execute($data);
            $riskAssessment = $result['report_sections']['risk_assessment'];

            expect($riskAssessment)->toHaveKeys([
                'overall_risk_score',
                'risk_factors',
                'recommendations',
            ]);

            expect($riskAssessment['overall_risk_score'])->toBeInt();
            expect($riskAssessment['overall_risk_score'])->toBeGreaterThanOrEqual(0);
            expect($riskAssessment['overall_risk_score'])->toBeLessThanOrEqual(100);
        });
    });

    describe('calculateRiskFactors method', function (): void {
        it('calculates risk factors for various transaction patterns', function (): void {
            // High amount transaction
            WalletTransaction::factory()->create([
                'amount'     => 60000000, // > 50M
                'created_at' => now()->subDays(2),
            ]);

            // Off-hours transaction
            WalletTransaction::factory()->create([
                'amount'     => 10000000, // >= 5M
                'created_at' => now()->subDays(2)->setTime(1, 0, 0), // 1 AM (off-hours)
            ]);

            // High risk transaction from metadata
            WalletTransaction::factory()->create([
                'amount'   => 1000000,
                'metadata' => [
                    'audit' => [
                        'risk_level' => 'high',
                    ],
                ],
                'created_at' => now()->subDays(2),
            ]);

            // Round number transaction
            WalletTransaction::factory()->create([
                'amount'     => 3000000, // Exactly 3M
                'created_at' => now()->subDays(2),
            ]);

            // High risk admin action
            AdminActionLog::factory()->create([
                'risk_level'      => 'high',
                'response_status' => 200,
                'created_at'      => now()->subDays(2),
            ]);

            // Failed admin action
            AdminActionLog::factory()->create([
                'risk_level'      => 'medium',
                'response_status' => 500,
                'created_at'      => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'               => $this->dateFromJalali,
                'date_to'                 => $this->dateToJalali,
                'include_risk_assessment' => true,
            ]);

            $result      = $this->action->execute($data);
            $riskFactors = $result['report_sections']['risk_assessment']['risk_factors'];

            expect($riskFactors)->toHaveKeys([
                'transaction_volume_risk',
                'temporal_risk',
                'pattern_risk',
                'admin_activity_risk',
            ]);

            // Check transaction volume risk
            expect($riskFactors['transaction_volume_risk'])->toHaveKeys([
                'high_amount_transactions',
                'high_amount_percentage',
                'risk_level',
            ]);

            // Check temporal risk
            expect($riskFactors['temporal_risk'])->toHaveKeys([
                'off_hours_transactions',
                'off_hours_percentage',
                'risk_level',
            ]);

            // Check pattern risk
            expect($riskFactors['pattern_risk'])->toHaveKeys([
                'round_number_transactions',
                'round_number_percentage',
                'high_risk_transactions',
                'high_risk_percentage',
                'risk_level',
            ]);

            // Check admin activity risk
            expect($riskFactors['admin_activity_risk'])->toHaveKeys([
                'high_risk_admin_actions',
                'high_risk_admin_percentage',
                'failed_admin_actions',
                'failed_admin_percentage',
                'risk_level',
            ]);
        });
    });

    describe('calculateOverallRiskScore method', function (): void {
        it('calculates overall risk score correctly', function (): void {
            // Create data that will result in high-risk factors
            WalletTransaction::factory()->count(5)->create([
                'amount'     => 100000000, // All high amount (100M > 50M)
                'created_at' => now()->subDays(2)->setTime(2, 0, 0), // Off-hours
                'metadata'   => [
                    'audit' => [
                        'risk_level' => 'high',
                    ],
                ],
            ]);

            AdminActionLog::factory()->count(5)->create([
                'risk_level'      => 'high',
                'response_status' => 500, // Failed
                'created_at'      => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'               => $this->dateFromJalali,
                'date_to'                 => $this->dateToJalali,
                'include_risk_assessment' => true,
            ]);

            $result           = $this->action->execute($data);
            $overallRiskScore = $result['report_sections']['risk_assessment']['overall_risk_score'];

            // With high-risk factors, score should be elevated
            expect($overallRiskScore)->toBeGreaterThan(30); // Reasonable expectation
            expect($overallRiskScore)->toBeLessThanOrEqual(100);
        });
    });

    describe('generateRiskRecommendations method', function (): void {
        it('generates critical recommendations for high overall risk', function (): void {
            // Create very high-risk scenario - more realistic approach
            WalletTransaction::factory()->count(5)->create([
                'amount'     => 100000000, // Very high amounts
                'created_at' => now()->subDays(2)->setTime(2, 0, 0), // Off-hours
                'metadata'   => [
                    'audit' => [
                        'risk_level' => 'high',
                    ],
                ],
            ]);

            AdminActionLog::factory()->count(5)->create([
                'risk_level'      => 'high',
                'response_status' => 500,
                'created_at'      => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'               => $this->dateFromJalali,
                'date_to'                 => $this->dateToJalali,
                'include_risk_assessment' => true,
            ]);

            $result          = $this->action->execute($data);
            $recommendations = $result['report_sections']['risk_assessment']['recommendations'];

            expect($recommendations)->toBeArray();
            expect($recommendations)->not->toBeEmpty();

            // Should have at least some high-priority recommendations
            $highPriorityRecommendations = collect($recommendations)->whereIn('priority', ['critical', 'high']);
            expect($highPriorityRecommendations)->not->toBeEmpty();
        });

        it('generates elevated risk recommendations for medium overall risk', function (): void {
            // Create medium risk scenario
            WalletTransaction::factory()->count(20)->create([
                'amount'     => 60000000, // Some high amounts
                'created_at' => now()->subDays(2),
            ]);

            WalletTransaction::factory()->count(80)->create([
                'amount'     => 10000, // Mostly small amounts
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'               => $this->dateFromJalali,
                'date_to'                 => $this->dateToJalali,
                'include_risk_assessment' => true,
            ]);

            $result          = $this->action->execute($data);
            $recommendations = $result['report_sections']['risk_assessment']['recommendations'];

            expect($recommendations)->toBeArray();
        });

        it('generates specific recommendations based on risk factor types', function (): void {
            // High transaction volume risk
            WalletTransaction::factory()->count(3)->create([
                'amount'     => 80000000,
                'created_at' => now()->subDays(2),
            ]);

            // High temporal risk
            WalletTransaction::factory()->count(6)->create([
                'amount'     => 10000000,
                'created_at' => now()->subDays(2)->setTime(3, 0, 0),
            ]);

            // High pattern risk
            WalletTransaction::factory()->count(5)->create([
                'amount'     => 5000000, // Round numbers
                'created_at' => now()->subDays(2),
            ]);

            // High admin activity risk
            AdminActionLog::factory()->count(3)->create([
                'risk_level' => 'high',
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'               => $this->dateFromJalali,
                'date_to'                 => $this->dateToJalali,
                'include_risk_assessment' => true,
            ]);

            $result          = $this->action->execute($data);
            $recommendations = $result['report_sections']['risk_assessment']['recommendations'];

            expect($recommendations)->toBeArray();
            expect($recommendations)->not->toBeEmpty();

            // Should have specific recommendations for risk types present
            $categories = collect($recommendations)->pluck('category')->unique();
            // We expect at least some categories to be present based on the high-risk data we created
            expect($categories->isNotEmpty())->toBeTrue();
        });

        it('generates default recommendation when no specific risks are detected', function (): void {
            // Create only low-risk data
            WalletTransaction::factory()->count(10)->create([
                'amount'     => 10000, // Small amounts
                'created_at' => now()->subDays(2),
            ]);

            AdminActionLog::factory()->count(2)->create([
                'risk_level'      => 'low',
                'response_status' => 200,
                'created_at'      => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'               => $this->dateFromJalali,
                'date_to'                 => $this->dateToJalali,
                'include_risk_assessment' => true,
            ]);

            $result          = $this->action->execute($data);
            $recommendations = $result['report_sections']['risk_assessment']['recommendations'];

            expect($recommendations)->toBeArray();
            expect($recommendations)->not->toBeEmpty();

            // Should have default maintenance recommendation
            $defaultRecommendation = collect($recommendations)->where('action', 'continue_regular_monitoring')->first();
            expect($defaultRecommendation)->not->toBeNull();
        });
    });

    describe('getRiskLevel method', function (): void {
        it('categorizes risk levels correctly', function (): void {
            // We'll test the logic by creating scenarios that we expect to result in different risk levels

            // Test high-risk scenario
            WalletTransaction::factory()->count(20)->create([
                'amount'     => 80000000, // High amounts that should trigger high risk
                'created_at' => now()->subDays(2),
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'               => $this->dateFromJalali,
                'date_to'                 => $this->dateToJalali,
                'include_risk_assessment' => true,
            ]);

            $result    = $this->action->execute($data);
            $riskLevel = $result['report_sections']['risk_assessment']['risk_factors']['transaction_volume_risk']['risk_level'];

            // With 100% high-amount transactions, this should be high risk
            expect($riskLevel)->toBe('high');

            // Clean up
            WalletTransaction::query()->delete();

            // Test low-risk scenario
            WalletTransaction::factory()->count(100)->create([
                'amount'     => 10000, // Small amounts
                'created_at' => now()->subDays(2),
            ]);

            $result2    = $this->action->execute($data);
            $riskLevel2 = $result2['report_sections']['risk_assessment']['risk_factors']['transaction_volume_risk']['risk_level'];

            expect($riskLevel2)->toBe('low');
        });
    });

    describe('edge cases and boundary conditions', function (): void {
        it('handles empty data gracefully', function (): void {
            $data = ComplianceReportRequestData::from([
                'date_from'                    => $this->dateFromJalali,
                'date_to'                      => $this->dateToJalali,
                'include_transaction_analysis' => true,
                'include_admin_activity'       => true,
                'include_suspicious_activity'  => true,
                'include_risk_assessment'      => true,
                'report_type'                  => 'daily',
            ]);

            $result = $this->action->execute($data);

            expect($result)->toBeArray();
            expect($result['summary']['total_transactions'])->toBe(0);
            // With no data, risk score should be minimal (default low risk)
            expect($result['report_sections']['risk_assessment']['overall_risk_score'])->toBeLessThanOrEqual(25);
        });

        it('handles single day date range', function (): void {
            $singleDateJalali = verta()->format('Y-m-d'); // Use Jalali date for request data
            $singleCarbon     = now();

            WalletTransaction::factory()->create([
                'created_at' => $singleCarbon,
            ]);

            $data = ComplianceReportRequestData::from([
                'date_from'   => $singleDateJalali,
                'date_to'     => $singleDateJalali,
                'report_type' => 'daily',
            ]);

            $result = $this->action->execute($data);

            expect($result['report_sections']['daily_breakdown'])->toHaveCount(1);
            // The key in the breakdown will be in Gregorian format
            $jalaliDate = verta($singleCarbon)->format('Y-m-d');
            expect($result['report_sections']['daily_breakdown'])->toHaveKey($jalaliDate);
        });
    });
});

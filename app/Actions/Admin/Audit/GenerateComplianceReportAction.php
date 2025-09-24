<?php

declare(strict_types=1);

namespace App\Actions\Admin\Audit;

use App\Data\Admin\Audit\ComplianceReportRequestData;
use App\Models\AdminActionLog;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class GenerateComplianceReportAction
{
    public function execute(ComplianceReportRequestData $data): array
    {
        $report = [
            'report_period' => [
                'from' => verta($data->date_from)->format('Y-m-d'),
                'to'   => verta($data->date_to)->format('Y-m-d'),
                'type' => $data->report_type,
            ],
            'summary'              => $this->generateSummary($data),
            'transaction_analysis' => $this->generateTransactionAnalysis($data),
        ];

        if ($data->include_transaction_analysis) {
            $report['report_sections']['transaction_analysis'] = $this->generateTransactionAnalysis($data);
        }
        if ($data->include_admin_activity) {
            $report['report_sections']['admin_activity'] = $this->generateAdminActionsSummary($data);
        }

        if ($data->include_suspicious_activity) {
            $report['report_sections']['suspicious_activity'] = $this->generateSuspiciousActivityReport($data);
        }

        if ($data->include_risk_assessment) {
            $report['report_sections']['risk_assessment'] = $this->generateRiskAssessmentReport($data);
        }

        if ($data->report_type === 'daily') {
            $report['report_sections']['daily_breakdown'] = $this->generateDailyBreakdown($data);
        }

        return $report;
    }

    private function generateSummary(ComplianceReportRequestData $data): array
    {
        $query = WalletTransaction::query()
            ->whereBetween('created_at', [$data->date_from, $data->date_to]);

        if ($data->user_ids) {
            $query->whereIn('user_id', $data->user_ids);
        }

        if ($data->transaction_types) {
            $query->whereIn('type', $data->transaction_types);
        }

        if ($data->min_amount || $data->max_amount) {
            $query->where(function ($q) use ($data): void {
                if ($data->min_amount) {
                    $q->where(DB::raw('ABS(amount)'), '>=', $data->min_amount);
                }
                if ($data->max_amount) {
                    $q->where(DB::raw('ABS(amount)'), '<=', $data->max_amount);
                }
            });
        }

        return [
            'total_transactions'       => $query->clone()->count(),
            'total_volume_rial'        => $query->clone()->sum('amount'),
            'unique_users'             => $query->clone()->distinct('user_id')->count(),
            'credits_count'            => $query->clone()->where('amount', '>', 0)->count(),
            'debits_count'             => $query->clone()->where('amount', '<', 0)->count(),
            'credits_volume'           => $query->clone()->where('amount', '>', 0)->sum('amount'),
            'debits_volume'            => abs((int) $query->clone()->where('amount', '<', 0)->sum('amount')),
            'large_transactions_count' => $query->clone()->where(DB::raw('ABS(amount)'), '>=', 5000000)->count(),
            'avg_transaction_amount'   => $query->clone()->avg(DB::raw('ABS(amount)')),
        ];
    }

    private function generateTransactionAnalysis(ComplianceReportRequestData $data): array
    {
        $query = WalletTransaction::query()
            ->whereBetween('created_at', [$data->date_from, $data->date_to]);

        if ($data->user_ids) {
            $query->whereIn('user_id', $data->user_ids);
        }

        // Transaction types breakdown
        $typeBreakdown = $query->clone()
            ->select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(ABS(amount)) as volume'))
            ->groupBy('type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->type->value => [
                    'count'  => $item->count,
                    'volume' => $item->volume,
                ]];
            });

        // Source types breakdown
        $sourceBreakdown = $query->clone()
            ->select('source_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(ABS(amount)) as volume'))
            ->groupBy('source_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->source_type->value => [
                    'count'  => $item->count,
                    'volume' => $item->volume,
                ]];
            });

        // High-risk transactions (from metadata)
        $highRiskCount = $query->clone()
            ->whereJsonContains('metadata->audit->risk_level', 'high')
            ->count();

        return [
            'by_type'                => $typeBreakdown->all(),
            'by_source'              => $sourceBreakdown->all(),
            'high_risk_transactions' => $highRiskCount,
        ];
    }

    private function generateAdminActionsSummary(ComplianceReportRequestData $data): array
    {
        $query = AdminActionLog::query()
            ->whereBetween('created_at', [$data->date_from, $data->date_to]);

        return [
            'total_admin_actions' => $query->count(),
            'unique_admins'       => $query->distinct('admin_id')->count(),
            'by_action_type'      => $query->clone()
                ->select('action_type', DB::raw('COUNT(*) as count'))
                ->groupBy('action_type', 'admin_id')
                ->pluck('count', 'action_type')
                ->toArray(),
            'by_risk_level' => $query->clone()
                ->select('risk_level', DB::raw('COUNT(*) as count'))
                ->groupBy('risk_level', 'admin_id')
                ->pluck('count', 'risk_level')
                ->toArray(),
            'failed_actions' => $query->clone()
                ->where('response_status', '>=', 400)
                ->count(),
        ];
    }

    private function generateSuspiciousActivityReport(ComplianceReportRequestData $data): array
    {
        $query = WalletTransaction::query()
            ->whereBetween('created_at', [$data->date_from, $data->date_to]);

        if ($data->user_ids) {
            $query->whereIn('user_id', $data->user_ids);
        }

        return [
            'large_transactions' => $query->clone()
                ->where(DB::raw('ABS(amount)'), '>=', 50000000)
                ->count(),
            'off_hours_transactions' => $query->clone()
                ->where(function ($q): void {
                    $q->whereTime('created_at', '<', '06:00:00')
                        ->orWhereTime('created_at', '>', '22:00:00');
                })
                ->where(DB::raw('ABS(amount)'), '>=', 5000000)
                ->count(),
            'high_frequency_users' => $query->clone()
                ->select('user_id', DB::raw('COUNT(*) as transaction_count'))
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) >= ?', [50])
                ->count(),
            'round_number_transactions' => $query->clone()
                ->where(DB::raw('ABS(amount) % 1000000'), '=', 0)
                ->where(DB::raw('ABS(amount)'), '>=', 1000000)
                ->count(),
        ];
    }

    private function generateDailyBreakdown(ComplianceReportRequestData $data): array
    {
        $breakdown = [];
        $current   = $data->date_from->clone();

        while ($current->lte($data->date_to)) {
            $dayStart = $current->clone()->startOfDay();
            $dayEnd   = $current->clone()->endOfDay();

            $dayQuery = WalletTransaction::query()
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            if ($data->user_ids) {
                $dayQuery->whereIn('user_id', $data->user_ids);
            }

            $breakdown[verta($current)->format('Y-m-d')] = [
                'total_transactions' => $dayQuery->count(),
                'total_volume'       => $dayQuery->sum(DB::raw('ABS(amount)')),
                'unique_users'       => $dayQuery->distinct('user_id')->count(),
                'admin_initiated'    => $dayQuery->whereJsonContains('metadata->audit->is_admin_initiated', true)->count(),
            ];

            $current->addDay();
        }

        return $breakdown;
    }

    private function generateRiskAssessmentReport(ComplianceReportRequestData $data): array
    {
        $transactionQuery = WalletTransaction::query()
            ->whereBetween('created_at', [$data->date_from, $data->date_to]);

        if ($data->user_ids) {
            $transactionQuery->whereIn('user_id', $data->user_ids);
        }

        $adminQuery = AdminActionLog::query()
            ->whereBetween('created_at', [$data->date_from, $data->date_to]);

        // Calculate risk factors
        $riskFactors = $this->calculateRiskFactors($transactionQuery->get(), $adminQuery->get());

        // Calculate overall risk score (0-100)
        $overallRiskScore = $this->calculateOverallRiskScore($riskFactors);

        // Generate recommendations based on risk factors
        $recommendations = $this->generateRiskRecommendations($riskFactors, $overallRiskScore);

        return [
            'overall_risk_score' => $overallRiskScore,
            'risk_factors'       => $riskFactors,
            'recommendations'    => $recommendations,
        ];
    }

    private function calculateRiskFactors(Collection $transactions, Collection $adminActions): array
    {
        $totalTransactions = $transactions->count();
        $totalAdminActions = $adminActions->count();

        // Transaction-based risk factors
        $highAmountTransactions = $transactions->filter(function ($tx) {
            return abs($tx->amount) >= 50000000; // 50M+ IRR
        })->count();

        $offHoursTransactions = $transactions->filter(function ($tx) {
            $hour = Carbon::parse($tx->created_at)->hour;

            return ($hour < 6 || $hour > 22) && abs($tx->amount) >= 5000000;
        })->count();

        $highRiskTransactions = $transactions->filter(function ($tx) {
            return isset($tx->metadata['audit']['risk_level']) && $tx->metadata['audit']['risk_level'] === 'high';
        })->count();

        $roundNumberTransactions = $transactions->filter(function ($tx) {
            return abs($tx->amount) % 1000000 === 0 && abs($tx->amount) >= 1000000;
        })->count();

        // Admin action-based risk factors
        $highRiskAdminActions = $adminActions->filter(function ($action) {
            return $action->risk_level === 'high';
        })->count();

        $failedAdminActions = $adminActions->filter(function ($action) {
            return $action->response_status >= 400;
        })->count();

        // Calculate risk percentages
        $highAmountPercentage    = $totalTransactions > 0 ? ($highAmountTransactions / $totalTransactions)  * 100 : 0;
        $offHoursPercentage      = $totalTransactions > 0 ? ($offHoursTransactions / $totalTransactions)    * 100 : 0;
        $highRiskPercentage      = $totalTransactions > 0 ? ($highRiskTransactions / $totalTransactions)    * 100 : 0;
        $roundNumberPercentage   = $totalTransactions > 0 ? ($roundNumberTransactions / $totalTransactions) * 100 : 0;
        $highRiskAdminPercentage = $totalAdminActions > 0 ? ($highRiskAdminActions / $totalAdminActions)    * 100 : 0;
        $failedAdminPercentage   = $totalAdminActions > 0 ? ($failedAdminActions / $totalAdminActions)      * 100 : 0;

        return [
            'transaction_volume_risk' => [
                'high_amount_transactions' => $highAmountTransactions,
                'high_amount_percentage'   => round($highAmountPercentage, 2),
                'risk_level'               => $this->getRiskLevel($highAmountPercentage, [5, 15]),
            ],
            'temporal_risk' => [
                'off_hours_transactions' => $offHoursTransactions,
                'off_hours_percentage'   => round($offHoursPercentage, 2),
                'risk_level'             => $this->getRiskLevel($offHoursPercentage, [10, 25]),
            ],
            'pattern_risk' => [
                'round_number_transactions' => $roundNumberTransactions,
                'round_number_percentage'   => round($roundNumberPercentage, 2),
                'high_risk_transactions'    => $highRiskTransactions,
                'high_risk_percentage'      => round($highRiskPercentage, 2),
                'risk_level'                => $this->getRiskLevel(max($roundNumberPercentage, $highRiskPercentage), [8, 20]),
            ],
            'admin_activity_risk' => [
                'high_risk_admin_actions'    => $highRiskAdminActions,
                'high_risk_admin_percentage' => round($highRiskAdminPercentage, 2),
                'failed_admin_actions'       => $failedAdminActions,
                'failed_admin_percentage'    => round($failedAdminPercentage, 2),
                'risk_level'                 => $this->getRiskLevel(max($highRiskAdminPercentage, $failedAdminPercentage), [3, 10]),
            ],
        ];
    }

    private function calculateOverallRiskScore(array $riskFactors): int
    {
        $score   = 0;
        $weights = [
            'transaction_volume_risk' => 30,
            'temporal_risk'           => 20,
            'pattern_risk'            => 25,
            'admin_activity_risk'     => 25,
        ];

        foreach ($riskFactors as $category => $data) {
            $riskLevel     = $data['risk_level'];
            $categoryScore = match ($riskLevel) {
                'high'   => 80,
                'medium' => 50,
                'low'    => 20,
                // @codeCoverageIgnoreStart
                default => 0,
                // @codeCoverageIgnoreEnd
            };

            $score += ($categoryScore * $weights[$category]) / 100;
        }

        return min(100, max(0, (int) round($score)));
    }

    private function generateRiskRecommendations(array $riskFactors, int $overallRiskScore): array
    {
        $recommendations = [];

        // High-level recommendations based on overall score
        if ($overallRiskScore >= 70) {
            $recommendations[] = [
                'priority' => 'critical',
                'category' => 'overall',
                'message'  => __('messages.audit.recommendations.critical_risk_level'),
                'action'   => 'immediate_review_required',
            ];
        } elseif ($overallRiskScore >= 50) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'overall',
                'message'  => __('messages.audit.recommendations.elevated_risk_level'),
                'action'   => 'enhanced_monitoring_recommended',
            ];
        }

        // Specific recommendations based on risk factors
        if ($riskFactors['transaction_volume_risk']['risk_level'] === 'high') {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'transaction_volume',
                'message'  => __('messages.audit.recommendations.high_volume_transactions'),
                'action'   => 'review_large_transactions',
            ];
        }

        if ($riskFactors['temporal_risk']['risk_level'] === 'high') {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'temporal',
                'message'  => __('messages.audit.recommendations.off_hours_activity'),
                'action'   => 'investigate_off_hours_patterns',
            ];
        }

        if ($riskFactors['pattern_risk']['risk_level'] === 'high') {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'pattern',
                'message'  => __('messages.audit.recommendations.suspicious_patterns'),
                'action'   => 'analyze_transaction_patterns',
            ];
        }

        if ($riskFactors['admin_activity_risk']['risk_level'] === 'high') {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'admin_activity',
                'message'  => __('messages.audit.recommendations.admin_activity_concerns'),
                'action'   => 'review_admin_permissions',
            ];
        }

        // Add default recommendation if no specific risks detected
        if (empty($recommendations)) {
            $recommendations[] = [
                'priority' => 'low',
                'category' => 'overall',
                'message'  => __('messages.audit.recommendations.maintain_monitoring'),
                'action'   => 'continue_regular_monitoring',
            ];
        }

        return $recommendations;
    }

    private function getRiskLevel(float $percentage, array $thresholds): string
    {
        [$mediumThreshold, $highThreshold] = $thresholds;

        if ($percentage >= $highThreshold) {
            return 'high';
        }
        if ($percentage >= $mediumThreshold) {
            return 'medium';
        }

        return 'low';
    }
}

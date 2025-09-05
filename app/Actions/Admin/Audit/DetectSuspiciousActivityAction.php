<?php

declare(strict_types=1);

namespace App\Actions\Admin\Audit;

use App\Data\Admin\Audit\SuspiciousActivityAgregratedData;
use App\Data\Admin\Audit\SuspiciousActivityCollectionData;
use App\Data\Admin\Audit\SuspiciousActivityData;
use App\Data\Admin\Audit\SuspiciousActivityRequestData;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DetectSuspiciousActivityAction
{
    public function handle(SuspiciousActivityRequestData $data): SuspiciousActivityAgregratedData
    {
        if ($data->include_large_amounts) {
            $large_transactions = $this->detectLargeTransactions($data);
        }

        if ($data->include_off_hours) {
            $off_hours_transactions = $this->detectOffHoursTransactions($data);
        }

        if ($data->include_high_frequency) {
            $high_frequency_users = $this->detectHighFrequencyUsers($data);
        }

        if ($data->include_round_numbers) {
            $round_number_patterns = $this->detectRoundNumberPatterns($data);
        }

        // Additional pattern detection
        $rapid_succession = $this->detectRapidSuccessionTransactions($data);
        $unusual_admin_activity = $this->detectUnusualAdminActivity($data);
        $suspiciousActivities = new SuspiciousActivityCollectionData(
            rapid_succession: $rapid_succession,
            unusual_admin_activity: $unusual_admin_activity,
            large_transactions: $large_transactions ?? null,
            off_hours_transactions: $off_hours_transactions ?? null,
            high_frequency_users: $high_frequency_users ?? null,
            round_number_patterns: $round_number_patterns ?? null,
        );
        return new SuspiciousActivityAgregratedData(
            detection_period: [
                'from' => verta($data->date_from)->format('Y-m-d'),
                'to' => verta($data->date_to)->format('Y-m-d'),
            ],
            detection_criteria: [
                'large_amount_threshold' => $data->large_amount_threshold,
                'high_frequency_threshold' => $data->high_frequency_threshold,
            ],
            suspicious_activities: $suspiciousActivities,
            summary: $this->generateSuspiciousActivitySummary($suspiciousActivities),
        );
    }

    private function detectLargeTransactions(SuspiciousActivityRequestData $data): Collection
    {
        $query = WalletTransaction::query()
            ->with(['user', 'wallet'])
            ->whereBetween('created_at', [$data->date_from, $data->date_to])
            ->where(DB::raw('ABS(amount)'), '>=', $data->large_amount_threshold);

        if ($data->user_ids) {
            $query->whereIn('user_id', $data->user_ids);
        }

        return $query->get()->map(function ($transaction) {
            return new SuspiciousActivityData(
                transaction_id: $transaction->id,
                user_id: $transaction->user_id,
                user_name: $transaction->user->first_name . ' ' . $transaction->user->last_name,
                amount: $transaction->amount,
                type: $transaction->type->value,
                created_at: $transaction->created_at->format('Y-m-d H:i:s'),
                hour: (string)$transaction->created_at->hour,
                flags: json_encode(['large_amount']),
                admin_initiated: $transaction->metadata['audit']['is_admin_initiated'] ?? false ? 'true' : 'false',
                ip_address: $transaction->metadata['audit']['ip_address'] ?? null,
            );
        });
    }

    private function detectOffHoursTransactions(SuspiciousActivityRequestData $data): Collection
    {
        $query = WalletTransaction::query()
            ->with(['user', 'wallet'])
            ->whereBetween('created_at', [$data->date_from, $data->date_to])
            ->where(function ($q) {
                $q->whereTime('created_at', '<', '06:00:00')
                  ->orWhereTime('created_at', '>', '22:00:00');
            })
            ->where(DB::raw('ABS(amount)'), '>=', 5000000); // Only significant amounts off hours

        if ($data->user_ids) {
            $query->whereIn('user_id', $data->user_ids);
        }

        return $query->get()->map(function ($transaction) {
            return new SuspiciousActivityData(
                transaction_id: $transaction->id,
                user_id: $transaction->user_id,
                user_name: $transaction->user->first_name . ' ' . $transaction->user->last_name,
                amount: $transaction->amount,
                type: $transaction->type->value,
                created_at: $transaction->created_at->format('Y-m-d H:i:s'),
                hour: (string)$transaction->created_at->hour,
                flags: json_encode(['off_hours']),
                admin_initiated: $transaction->metadata['audit']['is_admin_initiated'] ?? false ? 'true' : 'false',
                ip_address: $transaction->metadata['audit']['ip_address'] ?? null,
            );
        });
    }

    private function detectHighFrequencyUsers(SuspiciousActivityRequestData $data): Collection
    {
        $query = WalletTransaction::query()
            ->join('users', 'wallet_transactions.user_id', '=', 'users.id')
            ->select(
                'user_id',
                DB::raw('COUNT(wallet_transactions.id) as transaction_count'),
                DB::raw('SUM(ABS(wallet_transactions.amount)) as total_volume'),
                DB::raw('MIN(wallet_transactions.created_at) as first_transaction'),
                DB::raw('MAX(wallet_transactions.created_at) as last_transaction')
            )
            ->whereBetween('wallet_transactions.created_at', [$data->date_from, $data->date_to])
            ->with('user')
            ->groupBy('user_id')
            ->havingRaw('COUNT(wallet_transactions.id) >= ?', [$data->high_frequency_threshold]);

        if ($data->user_ids) {
            $query->whereIn('user_id', $data->user_ids);
        }

        return $query->get()->map(function ($result) {
            $user = $result->user;
            return new SuspiciousActivityData(
                transaction_id: 0,
                user_id: $result->user_id,
                user_name: $user->first_name . ' ' . $user->last_name,
                amount: 0,
                type: '',
                created_at: '',
                hour: '',
                flags: json_encode(['high_frequency']),
                admin_initiated: 'false',
                ip_address: null,
                transaction_count:(int)  $result->transaction_count,
                total_volume: (int) $result->total_volume,
                first_transaction: $result->first_transaction,
                last_transaction: $result->last_transaction,
                avg_transaction_amount: (string)round($result->total_volume / $result->transaction_count),
            );
        });
    }

    private function detectRoundNumberPatterns(SuspiciousActivityRequestData $data): Collection
    {
        $query = WalletTransaction::query()
            ->with(['user'])
            ->whereBetween('created_at', [$data->date_from, $data->date_to])
            ->where(DB::raw('ABS(amount) % 1000000'), '=', 0) // Exactly divisible by 1M IRR
            ->where(DB::raw('ABS(amount)'), '>=', 5000000); // At least 5M IRR

        if ($data->user_ids) {
            $query->whereIn('user_id', $data->user_ids);
        }

        return $query->get()->map(function ($transaction) {
            return new SuspiciousActivityData(
                transaction_id: $transaction->id,
                user_id: $transaction->user_id,
                user_name: $transaction->user->first_name . ' ' . $transaction->user->last_name,
                amount: $transaction->amount,
                type: $transaction->type->value,
                created_at: $transaction->created_at->format('Y-m-d H:i:s'),
                hour: (string)$transaction->created_at->hour,
                flags: json_encode(['round_numbers']),
                admin_initiated: $transaction->metadata['audit']['is_admin_initiated'] ?? false ? 'true' : 'false',
                ip_address: $transaction->metadata['audit']['ip_address'] ?? null,
            );
        });
    }

    private function detectRapidSuccessionTransactions(SuspiciousActivityRequestData $data): Collection
    {
        // Detect multiple large transactions within 5 minutes
        $rapidTransactions = WalletTransaction::query()
            ->select('user_id', 'created_at', 'amount', 'id')
            ->with(['user'])
            ->whereBetween('created_at', [$data->date_from, $data->date_to])
            ->where(DB::raw('ABS(amount)'), '>=', 10000000) // 10M IRR threshold
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy('user_id')
            ->filter(function ($userTransactions) {
                // Check if user has multiple transactions within 5 minutes
                for ($i = 0; $i < count($userTransactions) - 1; $i++) {
                    $timeDiff = $userTransactions[$i + 1]->created_at->diffInMinutes($userTransactions[$i]->created_at);
                    if ($timeDiff <= 5) {
                        return true;
                    }
                }
                return false;
            })
            ->flatten()
            ->unique('id');

        return $rapidTransactions->map(function ($transaction) {
            return new SuspiciousActivityData(
                transaction_id: $transaction->id,
                user_id: $transaction->user_id,
                user_name: $transaction->user->first_name . ' ' . $transaction->user->last_name,
                amount: $transaction->amount,
                type: $transaction->type->value,
                created_at: $transaction->created_at->format('Y-m-d H:i:s'),
                hour: (string)$transaction->created_at->hour,
                flags: json_encode(['rapid_succession']),
                admin_initiated: $transaction->metadata['audit']['is_admin_initiated'] ?? false ? 'true' : 'false',
                ip_address: $transaction->metadata['audit']['ip_address'] ?? null,
                pattern: 'Multiple large transactions within 5 minutes',
            );
        });
    }

    private function detectUnusualAdminActivity(SuspiciousActivityRequestData $data): Collection
    {
        // Detect high-volume admin-initiated transactions
        return WalletTransaction::query()
            ->with(['user'])
            ->whereBetween('created_at', [$data->date_from, $data->date_to])
            ->whereJsonContains('metadata->audit->is_admin_initiated', true)
            ->where(DB::raw('ABS(amount)'), '>=', 20000000) // 20M IRR threshold for admin actions
            ->get()
            ->map(function ($transaction) {
                return new SuspiciousActivityData(
                    transaction_id: $transaction->id,
                    user_id: $transaction->user_id,
                    user_name: $transaction->user->first_name.' '.$transaction->user->last_name,
                    amount: $transaction->amount,
                    type: $transaction->type->value,
                    created_at: $transaction->created_at->format('Y-m-d H:i:s'),
                    hour: (string) $transaction->created_at->hour,
                    flags: json_encode(['unusual_admin_activity']),
                    admin_initiated: 'true',
                    ip_address: $transaction->metadata['audit']['ip_address'] ?? null,
                    pattern: 'High-value admin-initiated transaction',
                );
            });
    }

    private function generateSuspiciousActivitySummary(SuspiciousActivityCollectionData $activities): array
    {
        $summary = [
            'total_suspicious_activities' => 0,
            'by_type' => [],
            'high_risk_count' => 0,
            'unique_users_involved' => 0,
        ];

        $allUserIds = collect();

        foreach ($activities as $type => $items) {
            $count = $items ? count($items) : 0;
            $summary['by_type'][$type] = $count;
            $summary['total_suspicious_activities'] += $count;

            // Collect user IDs
            if ($items instanceof Collection) {
                $allUserIds = $allUserIds->merge($items->pluck('user_id'));
            }
        }

        $summary['unique_users_involved'] = $allUserIds->unique()->count();

        return $summary;
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Admin\Audit;

use App\Data\Transformer\CarbonFromJalaliString;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

#[MapInputName(SnakeCaseMapper::class)]
final class ComplianceReportRequestData extends Data
{
    public function __construct(
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $date_from,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $date_to,
        public string $report_type = 'daily', // daily, monthly, custom
        public ?array $user_ids = null,
        public ?array $transaction_types = null,
        public ?int $min_amount = null,
        public ?int $max_amount = null,
        public bool $include_transaction_analysis = true,
        public bool $include_admin_activity = true,
        public bool $include_suspicious_activity = true,
        public bool $include_risk_assessment = false,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $now = verta()->format('Y-m-d');

        return [
            'date_from' => [
                'required', 'jdate:Y-m-d', 'jdate_before_equal:'.request('date_to').',Y-m-d',
            ],
            'date_to'                      => ['required', 'jdate:Y-m-d', 'jdate_before_equal:'.$now.',Y-m-d'],
            'report_type'                  => ['string', 'in:daily,monthly,custom'],
            'user_ids'                     => ['nullable', 'array'],
            'user_ids.*'                   => ['integer', 'exists:users,id'],
            'transaction_types'            => ['nullable', 'array'],
            'transaction_types.*'          => ['string'],
            'min_amount'                   => ['nullable', 'integer', 'min:0'],
            'max_amount'                   => ['nullable', 'integer', 'min:0', 'gt:min_amount'],
            'include_transaction_analysis' => ['boolean'],
            'include_admin_activity'       => ['boolean'],
            'include_suspicious_activity'  => ['boolean'],
            'include_risk_assessment'      => ['boolean'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'date_from' => [
                'description' => 'Start date for the report (Jalali format).',
                'example'     => '1402-01-01',
            ],
            'date_to' => [
                'description' => 'End date for the report (Jalali format).',
                'example'     => '1402-01-30',
            ],
            'report_type' => [
                'description' => 'Type of report: daily, monthly, or custom.',
                'example'     => 'daily',
            ],
            'user_ids' => [
                'description' => 'Array of user IDs to filter the report.',
                'example'     => [1, 2, 3],
            ],
            'user_ids.*' => [
                'description' => 'User ID for filtering.',
                'example'     => 1,
            ],
            'transaction_types' => [
                'description' => 'Array of transaction types to include.',
                'example'     => ['deposit', 'withdrawal'],
            ],
            'transaction_types.*' => [
                'description' => 'Transaction type.',
                'example'     => 'deposit',
            ],
            'min_amount' => [
                'description' => 'Minimum transaction amount.',
                'example'     => 10000,
            ],
            'max_amount' => [
                'description' => 'Maximum transaction amount.',
                'example'     => 100000,
            ],
            'include_transaction_analysis' => [
                'description' => 'Include transaction analysis in the report.',
                'example'     => true,
            ],
            'include_admin_activity' => [
                'description' => 'Include admin activity in the report.',
                'example'     => true,
            ],
            'include_suspicious_activity' => [
                'description' => 'Include suspicious activity in the report.',
                'example'     => true,
            ],
            'include_risk_assessment' => [
                'description' => 'Include risk assessment in the report.',
                'example'     => false,
            ],
        ];
    }
}

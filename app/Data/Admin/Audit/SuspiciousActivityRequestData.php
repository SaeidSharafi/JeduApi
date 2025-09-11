<?php

declare(strict_types=1);

namespace App\Data\Admin\Audit;

use App\Data\Transformer\CarbonFromJalaliString;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class SuspiciousActivityRequestData extends Data
{
    public function __construct(
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $date_from,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $date_to,
        public ?int $large_amount_threshold = 50000000, // 50M IRR
        public ?int $high_frequency_threshold = 10,     // 10+ transactions per day
        public bool $include_off_hours = true,
        public bool $include_large_amounts = true,
        public bool $include_high_frequency = true,
        public bool $include_round_numbers = true,
        public ?array $user_ids = null,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        $now = verta()->format('Y-m-d');

        return [
            'date_from'                => ['required', 'jdate:Y-m-d', 'jdate_before_equal:'.request('date_to').',Y-m-d'],
            'date_to'                  => ['required', 'jdate:Y-m-d', 'jdate_before_equal:'.$now.',Y-m-d'],
            'large_amount_threshold'   => ['nullable', 'integer', 'min:1000000'], // Min 1M IRR
            'high_frequency_threshold' => ['nullable', 'integer', 'min:5', 'max:100'],
            'include_off_hours'        => ['boolean'],
            'include_large_amounts'    => ['boolean'],
            'include_high_frequency'   => ['boolean'],
            'include_round_numbers'    => ['boolean'],
            'user_ids'                 => ['nullable', 'array'],
            'user_ids.*'               => ['integer', 'exists:users,id'],
        ];
    }
}

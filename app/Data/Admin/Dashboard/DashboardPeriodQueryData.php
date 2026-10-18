<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use App\Services\Dashboard\DashboardPeriod;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/** Query parameters for the period-scoped dashboard blocks. */
final class DashboardPeriodQueryData extends Data
{
    public function __construct(
        public string $period = DashboardPeriod::DEFAULT_KEY,
        public bool $fresh = false,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(DashboardPeriod::KEYS)],
            'fresh'  => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'period' => [
                'description' => 'Reporting window: today, the last 7 days, the last 30 days, or the current Jalali month.',
                'example'     => DashboardPeriod::DEFAULT_KEY,
            ],
            'fresh' => [
                'description' => 'Recompute the block and refresh its cache instead of serving the stored value.',
                'example'     => 1,
            ],
        ];
    }
}

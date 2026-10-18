<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/** Query parameters for the period-scoped dashboard blocks. */
final class DashboardPeriodQueryData extends Data
{
    public const string DEFAULT_PERIOD = '30d';

    public function __construct(
        public string $period = self::DEFAULT_PERIOD,
        public bool $fresh = false,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:today,7d,30d,mtd'],
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
                'example'     => self::DEFAULT_PERIOD,
            ],
            'fresh' => [
                'description' => 'Recompute the block and refresh its cache instead of serving the stored value.',
                'example'     => 1,
            ],
        ];
    }
}

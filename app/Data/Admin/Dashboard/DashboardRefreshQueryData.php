<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Query parameters for a dashboard block that is a snapshot of now.
 *
 * The period is deliberately absent so an ignored `period` query string is not
 * a validation error on the live and wallet blocks.
 */
final class DashboardRefreshQueryData extends Data
{
    public function __construct(public bool $fresh = false) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return ['fresh' => ['sometimes', 'boolean']];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'fresh' => [
                'description' => 'Recompute the block and refresh its cache instead of serving the stored value.',
                'example'     => 1,
            ],
        ];
    }
}

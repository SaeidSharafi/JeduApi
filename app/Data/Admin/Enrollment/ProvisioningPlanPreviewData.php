<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use Spatie\LaravelData\Data;

final class ProvisioningPlanPreviewData extends Data
{
    /** @param array<int, string> $added @param array<int, string> $removed @param array<int, string> $changed */
    public function __construct(public int $current_version, public int $next_version, public array $added, public array $removed, public array $changed) {}
}

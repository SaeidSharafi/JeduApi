<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use Spatie\LaravelData\Data;

final class ProvisioningDiagnosticData extends Data
{
    /**
     * @param  array<string, int|string>  $references
     */
    public function __construct(
        public string $provider,
        public string $status,
        public bool $retryable,
        public string $recommended_action,
        public ?string $safe_error,
        public array $references,
        public ?string $updated_at,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use Spatie\LaravelData\Data;

final class ProvisioningAttemptDiagnosticData extends Data
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $provider,
        public string $status,
        public bool $retryable,
        public int $sequence,
        public string $trigger,
        public ?string $failure_code,
        public ?string $classification,
        public ?string $safe_error,
        public ?string $correlation_id,
        public array $context,
        public ?string $created_at,
    ) {}
}

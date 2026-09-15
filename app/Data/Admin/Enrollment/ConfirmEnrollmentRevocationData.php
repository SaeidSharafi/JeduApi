<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use Spatie\LaravelData\Data;

/**
 * Manual confirmation that an externally unsupported revocation was performed
 * by a staff member outside the system.
 */
final class ConfirmEnrollmentRevocationData extends Data
{
    public function __construct(
        public readonly ?string $reason = null,
    ) {}

    public static function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
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
            'reason' => [
                'description' => 'Optional reason recorded with the manual confirmation.',
                'example'     => 'Access removed in the provider panel by support.',
            ],
        ];
    }
}

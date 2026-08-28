<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use App\Enums\EnrollmentStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class EnrollmentStatusChangeData extends Data
{
    public function __construct(
        public string $new_status,
        public ?string $reason,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'new_status' => ['required', 'string', Rule::enum(EnrollmentStatusEnum::class)],
            'reason'     => ['nullable', 'string', 'max:500', 'required_if:new_status,suspended,expired,cancelled'],
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
            'new_status' => [
                'description' => 'The new enrollment status.',
                'example'     => EnrollmentStatusEnum::ACTIVE->value,
            ],
            'reason' => [
                'description' => 'Reason for the status change.',
                'example'     => 'Payment confirmed, activating enrollment.',
            ],
        ];
    }
}

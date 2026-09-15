<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use App\Helpers\JalaliDateHelper;
use App\Rules\ValidNormalizedJalaliDateRule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class EnrollmentUpdateData extends Data
{
    public function __construct(
        public ?string $access_start_date,
        public ?string $access_end_date,
        public ?string $notes,
        public ?string $reason = null,
    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        return JalaliDateHelper::toGregorian($properties, [
            'access_start_date',
            'access_end_date',
        ]);
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'access_start_date' => ['bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d'],
            'access_end_date'   => [
                'bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d',
                'after_or_equal:access_start_date',
            ],
            'notes'  => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:500'],
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
            'access_start_date' => [
                'description' => 'The date access starts (Jalali date format).',
                'example'     => '1403-01-01',
            ],
            'access_end_date' => [
                'description' => 'The date access ends (Jalali date format). Must be on or after the start date.',
                'example'     => '1403-06-31',
            ],
            'notes' => [
                'description' => 'Internal notes about the enrollment.',
                'example'     => 'Extended at the customer request.',
            ],
            'reason' => [
                'description' => 'The reason for the enrollment update.',
                'example'     => 'Customer requested an extension.',
            ],
        ];
    }
}

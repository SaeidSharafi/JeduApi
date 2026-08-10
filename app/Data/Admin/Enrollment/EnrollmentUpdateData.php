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
        public ?int $external_enrollment_id,
        public ?string $notes,
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
            'access_start_date'      => ['bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d'],
            'access_end_date'        => ['bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d', 'after_or_equal:access_start_date'],
            'external_enrollment_id' => ['nullable', 'integer'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
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
                'description' => 'The start date for enrollment access.',
                'example'     => '2025-09-01',
            ],
            'access_end_date' => [
                'description' => 'The end date for enrollment access.',
                'example'     => '2025-12-31',
            ],
            'external_enrollment_id' => [
                'description' => 'External system enrollment ID.',
                'example'     => 12345,
            ],
            'notes' => [
                'description' => 'Admin notes for the enrollment.',
                'example'     => 'Extended access due to technical issues.',
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Shop\Teacher;

use App\Helpers\JalaliDateHelper;
use App\Rules\ValidNormalizedJalaliDateRule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class StoreAttendanceData extends Data
{
    public function __construct(
        public string $attendance_date,
        public ?int $occurrence_id,
        public array $attendances,
    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        return JalaliDateHelper::toGregorian($properties, [
            'attendance_date',
        ]);
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'attendance_date'             => ['bail', 'required', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d', 'before_or_equal:'.now()->format('Y-m-d')],
            'occurrence_id'               => ['nullable', 'integer'],
            'attendances'                 => ['required', 'array'],
            'attendances.*.attend_status' => ['nullable', 'numeric'],
            'attendances.*.enrolment_id'  => ['nullable', 'integer'],
            'attendances.*.attendance_id' => ['nullable', 'integer'],
            'attendances.*.notes'         => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'attendance_date' => [
                'description' => 'The date of the attendance record in Jalali format (YYYY-MM-DD).',
                'example'     => '1402-01-15',
            ],
            'occurrence_id' => [
                'description' => 'The occurrence ID of the attendance record.',
                'example'     => 123,
            ],
            'attendances' => [
                'description' => 'An array of attendance records.',
                'example'     => [
                    [
                        [
                            'attend_status' => 1,
                            'enrolment_id'  => 456,
                            'attendance_id' => 789,
                            'notes'         => 'Student was present.',
                        ],
                    ],
                ],
            ],
            'attendances.*.attend_status' => [
                'description' => 'The attendance status (e.g., 1 for present, 0 for absent).',
                'example'     => 1,
            ],
            'attendances.*.enrolment_id' => [
                'description' => 'The IMS enrolment ID of the student.',
                'example'     => 456,
            ],
            'attendances.*.attendance_id' => [
                'description' => 'The IMS attendance ID of the record.',
                'example'     => 789,
            ],
            'attendances.*.notes' => [
                'description' => 'Optional notes for the attendance record.',
                'example'     => 'Student was present.',
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Shop\Teacher;

use Hekmatinasser\Jalali\Exceptions\InvalidDatetimeException;
use Hekmatinasser\Jalali\Exceptions\InvalidUnitException;
use Hekmatinasser\Verta\Facades\Verta;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DeleteAttendanceData extends Data
{
    public function __construct(
        public string $attendance_date,
        public ?int $occurrence_id,
    ) {
    }

    public static function prepareForPipeline(array $properties): array
    {
        if (!empty($properties['attendance_date'])) {
            try {
                // Normalize separator
                $dateString = str_replace('/', '-', $properties['attendance_date']);

                // Convert Jalali to Gregorian Carbon instance
                $properties['attendance_date'] = Verta::parseFormat('Y-m-d', $dateString)
                    ->toCarbon()
                    ->format('Y-m-d');
            } catch (InvalidDatetimeException|InvalidUnitException $e) {
                $properties['attendance_date'] = 'invalid-jalali-date';
            }
        }

        return $properties;
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'attendance_date'             => ['required', 'date:Y-m-d'],
            'occurrence_id'               => ['nullable', 'integer'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'attendance_date'             => [
                'description' => 'The date of the attendance record in Jalali format (YYYY-MM-DD).',
                'example'     => '1402-01-15',
            ],
            'occurrence_id'               => [
                'description' => 'The occurrence ID of the attendance record.',
                'example'     => 123,
            ],
        ];
    }
}

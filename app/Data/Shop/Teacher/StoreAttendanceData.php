<?php

declare(strict_types=1);

namespace App\Data\Shop\Teacher;

use Hekmatinasser\Jalali\Exceptions\InvalidDatetimeException;
use Hekmatinasser\Jalali\Exceptions\InvalidUnitException;
use Hekmatinasser\Verta\Facades\Verta;
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

    public static function rules(ValidationContext $context): array
    {
        return [
            'attendance_date'             => ['required', 'date', 'before_or_equal:' . now()->format('Y-m-d')],
            'occurrence_id'               => ['nullable', 'integer'],
            'attendances'                 => ['required', 'array'],
            'attendances.*.attend_status' => ['nullable', 'numeric'],
            'attendances.*.enrolment_id'  => ['nullable', 'integer'],
            'attendances.*.attendance_id' => ['nullable', 'integer'],
            'attendances.*.notes'         => ['nullable', 'string', 'max:1000']
        ];
    }
}

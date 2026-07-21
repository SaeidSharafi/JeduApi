<?php

declare(strict_types=1);

namespace App\Data\Shop\Teacher;

use Hekmatinasser\Jalali\Exceptions\InvalidDatetimeException;
use Hekmatinasser\Jalali\Exceptions\InvalidUnitException;
use Hekmatinasser\Verta\Facades\Verta;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ShowAttendanceData extends Data
{
    public function __construct(
        public ?string $attendance_date,
        public ?int $occurrence_id,
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
            'attendance_date'             => ['nullable', 'date', 'before_or_equal:' . now()->format('Y-m-d')],
            'occurrence_id'               => ['nullable', 'integer'],
        ];
    }
}

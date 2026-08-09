<?php

namespace App\Helpers;

use Hekmatinasser\Jalali\Exceptions\InvalidDatetimeException;
use Hekmatinasser\Jalali\Exceptions\InvalidUnitException;
use Hekmatinasser\Verta\Facades\Verta;

class JalaliDateHelper
{
    /**
     * Convert specified Jalali date fields in an array to Gregorian Y-m-d.
     *
     * @param array<string, mixed> $properties
     * @param array<int, string> $fields
     * @return array<string, mixed>
     */
    public static function toGregorian(array $properties, array $fields): array
    {
        foreach ($fields as $field) {
            if (!empty($properties[$field]) && is_string($properties[$field])) {
                $properties[$field] = static::parseSingleDate($properties[$field]);
            }
        }

        return $properties;
    }

    private static function parseSingleDate(string $value): string
    {
        try {
            // Normalize separator
            $dateString = str_replace('/', '-', $value);

            // Convert Jalali to Gregorian Carbon instance
            return Verta::parseFormat('Y-m-d', $dateString)
                ->toCarbon()
                ->format('Y-m-d');
        } catch (InvalidDatetimeException|InvalidUnitException $e) {
            // Returning an invalid string ensures Laravel's 'date' validation rule fails cleanly
            return 'invalid-jalali-date';
        }
    }
}

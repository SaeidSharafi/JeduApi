<?php

declare(strict_types=1);

namespace App\Helpers;

use Hekmatinasser\Jalali\Exceptions\InvalidDatetimeException;
use Hekmatinasser\Jalali\Exceptions\InvalidUnitException;
use Hekmatinasser\Verta\Facades\Verta as VertaFacade;
use Hekmatinasser\Verta\Verta;
use InvalidArgumentException;

final class JalaliDateHelper
{
    public const string INVALID_DATE = '__invalid_jalali_date__';

    private const string INVALID_FORMAT = '__invalid_jalali_date_format__';

    /**
     * Convert specified Jalali date fields in an array to Gregorian Y-m-d.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<int|string, string>  $fields
     * @return array<string, mixed>
     */
    public static function toGregorian(array $properties, array $fields): array
    {
        foreach ($fields as $key => $fieldOrFormat) {
            $field  = is_string($key) ? $key : $fieldOrFormat;
            $format = is_string($key) ? $fieldOrFormat : 'Y-m-d';
            $value  = data_get($properties, $field);

            if (! empty($value) && is_string($value)) {
                data_set($properties, $field, self::parseSingleDate($value, $format));
            }
        }

        return $properties;
    }

    private static function parseSingleDate(string $value, string $format): string
    {
        $dateString = str_replace('/', '-', $value);
        $parts      = self::dateParts($dateString, $format);

        if ($parts === null) {
            return self::INVALID_FORMAT;
        }

        if (! Verta::isValidDate($parts['year'], $parts['month'], $parts['day'])
            || ! Verta::isValidTime($parts['hour'], $parts['minute'], $parts['second'])) {
            return self::INVALID_DATE;
        }

        try {
            return VertaFacade::parseFormat($format, $dateString)
                ->toCarbon()
                ->format($format);
        } catch (InvalidArgumentException|InvalidDatetimeException|InvalidUnitException) {
            return self::INVALID_DATE;
        }
    }

    /**
     * @return array{year: int, month: int, day: int, hour: int, minute: int, second: int}|null
     */
    private static function dateParts(string $value, string $format): ?array
    {
        $pattern = match ($format) {
            'Y-m-d'       => '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})$/',
            'Y-m-d H:i:s' => '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2}) (?<hour>\d{2}):(?<minute>\d{2}):(?<second>\d{2})$/',
            default       => null,
        };

        if ($pattern === null || preg_match($pattern, $value, $matches) !== 1) {
            return null;
        }

        return [
            'year'   => (int) $matches['year'],
            'month'  => (int) $matches['month'],
            'day'    => (int) $matches['day'],
            'hour'   => (int) ($matches['hour'] ?? 0),
            'minute' => (int) ($matches['minute'] ?? 0),
            'second' => (int) ($matches['second'] ?? 0),
        ];
    }
}

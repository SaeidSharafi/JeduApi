<?php

declare(strict_types=1);

namespace App\Helpers;

final class PhoneNumberHelper
{
    /**
     * Canonical storage form: a single leading zero (e.g. "09351234567").
     */
    public static function normalize(string $phone): string
    {
        return '0'.mb_ltrim($phone, '0');
    }

    /**
     * Canonical comparison form: no leading zero (e.g. "9351234567").
     */
    public static function withoutLeadingZero(string $phone): string
    {
        return mb_ltrim($phone, '0');
    }

    /**
     * Storage variants for a given input so lookups match rows stored either
     * with or without the leading zero (e.g. "09351234567" and "9351234567").
     *
     * @return array{string, string}
     */
    public static function lookupVariants(string $phone): array
    {
        $canonical = self::withoutLeadingZero($phone);

        return [$canonical, '0'.$canonical];
    }
}

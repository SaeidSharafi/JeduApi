<?php

declare(strict_types=1);

namespace App\Helpers;

final class PersianDigitHelper
{
    /** @var array<string, string> */
    private const array DIGITS = [
        "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3',
        "\u{0664}" => '4', "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7',
        "\u{0668}" => '8', "\u{0669}" => '9',
        "\u{06F0}" => '0', "\u{06F1}" => '1', "\u{06F2}" => '2', "\u{06F3}" => '3',
        "\u{06F4}" => '4', "\u{06F5}" => '5', "\u{06F6}" => '6', "\u{06F7}" => '7',
        "\u{06F8}" => '8', "\u{06F9}" => '9',
    ];

    /**
     * Convert Persian and Arabic-Indic digits to their ASCII equivalents.
     */
    public static function toAscii(?string $value): string
    {
        return strtr((string) $value, self::DIGITS);
    }
}

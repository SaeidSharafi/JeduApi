<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class IbanNumberRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value) || ! $this->validateIranianSheba($value)) {
            $fail('validation.custom.enrolment.optout.iban_number_is_invalid')->translate();
        }
    }

    private function validateIranianSheba(string $str): bool
    {
        // Check length
        if (mb_strlen($str) !== 26) {
            return false;
        }

        // Check pattern
        if (! preg_match('/^IR[0-9]{24}$/', $str)) {
            return false;
        }
        if (app()->environment() !== 'production') {
            return true;
        }
        // Prepare string for MOD 97-10 calculation
        $newStr = mb_substr($str, 4);
        $d1     = ord($str[0]) - 65 + 10; // Convert 'I' to numeric value
        $d2     = ord($str[1]) - 65 + 10; // Convert 'R' to numeric value
        $newStr .= $d1.$d2.mb_substr($str, 2, 2);

        // Calculate MOD 97-10
        $remainder = $this->iso7064Mod97_10($newStr);

        return $remainder === 1;
    }

    private function iso7064Mod97_10(string $iban): int
    {
        $remainder = $iban;

        while (mb_strlen($remainder) > 2) {
            $block     = mb_substr($remainder, 0, min(9, mb_strlen($remainder)));
            $remainder = (int) $block % 97 .mb_substr($remainder, mb_strlen($block));
        }

        return (int) $remainder % 97;
    }
}

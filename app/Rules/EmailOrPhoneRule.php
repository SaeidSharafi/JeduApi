<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class EmailOrPhoneRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (preg_match("/^0?9[0-1-2-3-9]\d{8}$/", $value)) {
            return;
        }
        if (preg_match("/^0[1-8]\d{9}$/", $value)) {
            return;
        }

        $fail(__('validation.email_phone_invalid'));
    }
}

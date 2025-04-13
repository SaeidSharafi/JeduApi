<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IranMobilePhoneRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match("/^0?9[0-1-2-3-9]\d{8}$/", $value)) {
            return;
        }

        if (preg_match("/^0[1-8]\d{9}$/", $value)) {
            return;
        }
        $fail(__('validation.iran_mobile_phone', ['attribute' => $attribute]));
    }
}

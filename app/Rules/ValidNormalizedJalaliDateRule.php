<?php

declare(strict_types=1);

namespace App\Rules;

use App\Helpers\JalaliDateHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ValidNormalizedJalaliDateRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === JalaliDateHelper::INVALID_DATE) {
            $fail(__('validation.invalid_jalali_date'));
        }
    }
}

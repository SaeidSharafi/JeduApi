<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class IranMobilePhoneRule implements ValidationRule
{
    public function __construct(private bool $mobileOnly = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match("/^0?9[0-1-2-3-9]\d{8}$/", $value)) {
            return;
        }
        if (! $this->mobileOnly && preg_match("/^0[1-8]\d{9}$/", $value)) {
            return;
        }
        $fail(__('validation.iran_mobile_phone', ['attribute' => $attribute]));
    }
}

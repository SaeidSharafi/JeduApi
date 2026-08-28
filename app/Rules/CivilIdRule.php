<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\User\CivilIdTypeEnum;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class CivilIdRule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * Set the data under validation.
     * This method is automatically called by Laravel.
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $idTypeInput = $this->data['civil_id_type'] ?? null;

        if (empty($value) || ! is_string($value) || ! is_string($idTypeInput)) {
            // This rule shouldn't run if the type is missing.
            // The 'required' rule on id_type should catch this, but this makes our rule robust.
            return;
        }

        $idTypeEnum = CivilIdTypeEnum::tryFrom($idTypeInput);
        if (is_null($idTypeEnum)) {
            // The id_type value was invalid (e.g., 'invalid_type').
            // The Enum rule on id_type will catch this, but again, we are being safe.
            return;
        }

        $isValid = match ($idTypeEnum) {
            CivilIdTypeEnum::NATIONAL_CODE  => $this->validateNationalCode($value),
            CivilIdTypeEnum::IMMIGRANT_CODE => $this->validateImmigrantCode($value),
            CivilIdTypeEnum::PASSPORT       => $this->validatePassport($value),
        };

        if (! $isValid) {
            $fail(__('validation.custom.civil_id.wrong', ['type' => $idTypeEnum->translate()]));
        }

    }

    private function validateNationalCode(string $value): bool
    {
        if (preg_match('/^\d{10}$/', $value) !== 1) {
            return false;
        }
        if (config('app.ignore_national_code_validation')) {
            return true;
        }
        for ($i = 0; $i < 10; $i++) {
            if (preg_match('/^'.$i.'{10}$/', $value)) {
                return false;
            }
        }

        for ($i = 0, $sum = 0; $i < 9; $i++) {
            $sum += ((10 - $i) * (int) (mb_substr($value, $i, 1)));
        }

        $ret    = $sum % 11;
        $parity = (int) (mb_substr($value, 9, 1));
        if (($ret < 2 && $ret === $parity) || ($ret >= 2 && $ret === 11 - $parity)) {
            return true;
        }

        return false;
    }

    /**
     * Validates an Immigrant/Faragir Code (کد فراگیر).
     */
    private function validateImmigrantCode(string $value): bool
    {
        return preg_match('/^\d{8}$|^\d{10}$|^\d{12}$|^\d{14}$/', $value) === 1;
    }

    /**
     * Performs a general, non-strict validation for a Passport Number.
     */
    private function validatePassport(string $value): bool
    {
        // General rule: 6-20 alphanumeric characters, case-insensitive.
        return preg_match('/^[A-Z0-9]{6,20}$/i', $value) === 1;
    }
}

<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Sms\SmsNotificationOptionEnum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects unknown keys inside the SMS notification `options` map.
 *
 * Silently dropping an unrecognised notification toggle would leave a
 * configuration that never fires, so the typo has to surface as a `422`
 * instead of being ignored like every other unknown settings key.
 */
final class SmsNotificationOptionKeyRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $knownKeys = array_map(
            static fn (SmsNotificationOptionEnum $option): string => $option->value,
            SmsNotificationOptionEnum::cases(),
        );

        $unknownKeys = array_diff(array_map(strval(...), array_keys($value)), $knownKeys);

        if ($unknownKeys !== []) {
            $fail(__('sms.errors.unknown_notification_option', ['option' => (string) reset($unknownKeys)]));
        }
    }
}

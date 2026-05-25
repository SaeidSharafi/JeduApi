<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Redacts sensitive/secret fields from integration setting values.
 *
 * Only fields explicitly listed per setting key are redacted.
 * All other fields pass through unchanged.
 */
final class SettingSecretRedactor
{
    public const string REDACTED = '***REDACTED***';

    /**
     * Secret field names per setting key.
     *
     * @var array<string, list<string>>
     */
    private const array SECRET_FIELDS = [
        'ims'             => ['api_key'],
        'moodle'          => ['token', 'auth_userkey_token'],
        'big_blue_button' => ['secret', 'default_attendee_password', 'default_moderator_password'],
        'spot_player'     => ['api_key'],
    ];

    /**
     * Redact secret fields from a setting value array.
     *
     * @param  string  $settingKey  The setting's key (e.g. 'moodle').
     * @param  mixed  $value  The raw value (array or scalar).
     * @return mixed Value with secrets replaced by REDACTED constant.
     */
    public function redact(string $settingKey, mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $secretFields = self::SECRET_FIELDS[$settingKey] ?? [];

        if ($secretFields === []) {
            return $value;
        }

        foreach ($secretFields as $field) {
            if (array_key_exists($field, $value)) {
                $value[$field] = self::REDACTED;
            }
        }

        return $value;
    }

    /**
     * Returns true if the given setting key has any registered secret fields.
     */
    public function hasSecrets(string $settingKey): bool
    {
        return isset(self::SECRET_FIELDS[$settingKey]);
    }
}

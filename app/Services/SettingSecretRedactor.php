<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\System\SettingKeyEnum;

/**
 * Redacts sensitive/secret fields from setting values before API responses and
 * audit logging.
 *
 * The secret field list is derived from {@see SettingKeyEnum::secretFields()},
 * the single registry of encrypted fields, so a field cannot be encrypted at
 * rest without also being redacted on read. All other fields pass through
 * unchanged.
 */
final class SettingSecretRedactor
{
    public const string REDACTED = '***REDACTED***';

    /**
     * Secret field names per setting key, derived from the enum registry.
     *
     * @var array<string, list<string>>|null
     */
    private static ?array $secretFields = null;

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

        $secretFields = self::registry()[$settingKey] ?? [];

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
        return isset(self::registry()[$settingKey]);
    }

    /**
     * Builds the setting key to secret field map once per process.
     *
     * @return array<string, list<string>>
     */
    private static function registry(): array
    {
        if (self::$secretFields === null) {
            $secretFields = [];

            foreach (SettingKeyEnum::cases() as $key) {
                $fields = $key->secretFields();

                if ($fields !== []) {
                    $secretFields[$key->value] = $fields;
                }
            }

            self::$secretFields = $secretFields;
        }

        return self::$secretFields;
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Provisioning;

use Spatie\LaravelData\Data;

/**
 * One provisioning provider as the admin panel edits it.
 *
 * Every provider declares its flat field schema and its request rules in one
 * class, so the form the API serves and the validator change together. The
 * computed readiness is derived from that same schema, not from the provider
 * adapter, so the badge and the form cannot disagree either.
 */
abstract class ProvisioningProviderSettingData extends Data
{
    /**
     * Presentation contract for the provider form: group name to field list.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    abstract public static function schema(): array;

    /**
     * Every declared field, flattened out of its schema group.
     *
     * @return list<array<string, mixed>>
     */
    final public static function fields(): array
    {
        $fields = [];

        foreach (static::schema() as $group) {
            foreach ($group as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Field key to translated label for every required field that carries a
     * connection value.
     *
     * `enabled` is a switch rather than a value, so boolean fields are excluded:
     * a disabled-but-complete provider is configured, not broken. That is also
     * exactly what each adapter's own configuration check reports.
     *
     * @return array<string, string>
     */
    final public static function requiredFields(): array
    {
        $fields = [];

        foreach (static::fields() as $field) {
            if (($field['required'] ?? false) && ($field['type'] ?? null) !== 'boolean') {
                $fields[$field['key']] = (string) $field['label'];
            }
        }

        return $fields;
    }

    /**
     * Whether every required connection field is filled.
     *
     * @param  array<string, mixed>  $settings
     */
    final public static function isConfigured(array $settings): bool
    {
        foreach (array_keys(static::requiredFields()) as $field) {
            if (($settings[$field] ?? null) === null || $settings[$field] === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Prepare a raw request payload for validation.
     *
     * A form sends an empty string for every connection input the admin left
     * blank, which means "not provided" for those fields. A sensitive field is
     * the exception: its empty string is the documented way to clear the stored
     * secret, so it must reach the save action untouched. The providers route is
     * exempt from Laravel's empty-string-to-null conversion for that reason.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    final public static function normalizePayload(array $payload): array
    {
        foreach (static::fields() as $field) {
            $key = $field['key'];

            if (($field['sensitive'] ?? false) === true) {
                continue;
            }

            if (($payload[$key] ?? null) === '') {
                $payload[$key] = null;
            }
        }

        return $payload;
    }
}

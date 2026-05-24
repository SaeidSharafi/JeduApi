<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\System\CacheKeysEnum;
use App\Enums\System\SettingKeyEnum;
use App\Models\AdminActionLog;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use SmartCache\Facades\SmartCache;

final class SettingsService
{
    /**
     * Integration keys store credentials/config — no media fields.
     * Skipping witImages avoids unnecessary DB queries for these keys.
     */
    private const array SKIP_MEDIA = [
        SettingKeyEnum::IMS,
        SettingKeyEnum::MOODLE,
        SettingKeyEnum::BIG_BLUE_BUTTON,
        SettingKeyEnum::SPOT_PLAYER,
    ];

    public function get(SettingKeyEnum $key, mixed $default = null): mixed
    {
        $allSettings = $this->getAll();

        // Retrieve the specific setting model from the collection.
        $setting = $allSettings->get($key->value);

        // If the setting doesn't exist, return the default.
        if (! $setting) {
            return $default;
        }

        $value = $setting->value;

        // Decrypt secret fields for integration keys (backward-compatible: plaintext passes through).
        $secretFields = $key->secretFields();

        if (is_array($value) && $secretFields !== []) {
            foreach ($secretFields as $field) {
                if (isset($value[$field]) && is_string($value[$field])) {
                    $value[$field] = $this->tryDecrypt($value[$field]);
                }
            }
        }

        // Integration keys store credentials — skip media resolution.
        if (is_array($value) && ! empty($value) && ! in_array($key, self::SKIP_MEDIA, true)) {
            return Setting::witImages($value);
        }

        return $value;
    }

    /**
     * Persists a setting value, invalidates the cache, and returns the Setting model.
     */
    public function set(SettingKeyEnum $key, mixed $value, string $type = 'json', ?string $group = null): Setting
    {
        // Encrypt secret fields for integration keys before persisting.
        $secretFields = $key->secretFields();

        if (is_array($value) && $secretFields !== []) {
            // Load existing stored value once — needed to restore placeholders.
            $existing = null;

            foreach ($secretFields as $field) {
                if (! isset($value[$field]) || ! is_string($value[$field])) {
                    continue;
                }

                // Placeholder received — preserve the existing stored secret instead of overwriting.
                if ($value[$field] === SettingSecretRedactor::REDACTED) {
                    if ($existing === null) {
                        $existing = Setting::where('key', $key->value)->value('value');

                        if (is_string($existing)) {
                            $existing = json_decode($existing, true) ?? [];
                        }
                    }

                    $value[$field] = $existing[$field] ?? '';

                    continue;
                }

                // Normal explicit update — encrypt non-empty value.
                if ($value[$field] !== '') {
                    $value[$field] = Crypt::encryptString($value[$field]);
                }
            }
        }

        $setting = Setting::setValue($key, $value, $type, $group);
        $this->forget();
        $this->auditIntegrationWrite($key, $value);

        return $setting;
    }

    /**
     * Forgets the settings cache. This is our public method for invalidation.
     */
    public function forget(): void
    {
        SmartCache::forget(CacheKeysEnum::Settings->value);
    }

    /**
     * Creates an audit log entry when an integration setting is written.
     * Secret fields are redacted — never stored in the log.
     */
    private function auditIntegrationWrite(SettingKeyEnum $key, mixed $value): void
    {
        if (! in_array($key, self::SKIP_MEDIA, true)) {
            return;
        }

        if (! auth('staff')->check()) {
            return;
        }

        $redactor  = new SettingSecretRedactor();
        $safeValue = $redactor->redact($key->value, $value);

        AdminActionLog::create([
            'admin_id'        => auth('staff')->id(),
            'action_type'     => 'update',
            'resource_type'   => 'integration_setting',
            'resource_id'     => null,
            'route_name'      => 'settings.integration.'.$key->value,
            'http_method'     => 'PUT',
            'request_data'    => ['key' => $key->value, 'value' => $safeValue],
            'response_status' => 200,
            'ip_address'      => request()->ip(),
            'user_agent'      => request()->userAgent(),
            'session_id'      => session()->getId(),
            'risk_level'      => 'high',
            'metadata'        => [
                'timestamp'       => now()->toISOString(),
                'integration_key' => $key->value,
            ],
        ]);
    }

    /**
     * Retrieves the entire collection of settings.
     * If not in the cache, it loads from the DB and caches it forever.
     */
    private function getAll(): Collection
    {
        return SmartCache::rememberForever(CacheKeysEnum::Settings->value, function () {
            // This closure only runs if the cache is empty.
            // It hits the database ONCE and then stores the result.
            return Setting::all()->keyBy('key');
        });
    }

    /**
     * Attempts to decrypt a string value.
     * Returns the original value if decryption fails (plaintext legacy value).
     */
    private function tryDecrypt(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Plaintext legacy value — return as-is for backward compatibility.
            return $value;
        }
    }
}

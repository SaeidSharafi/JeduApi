<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Enums\System\SettingKeyEnum;
use App\Models\AdminActionLog;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

final class SettingsService
{
    /**
     * Keys on the admin integration settings surface — credentials only, never media.
     *
     * These keys skip `witImages()`: their payloads cannot contain media references,
     * and the payment gateways (whose icon is hydrated) must keep resolving media.
     */
    private const array INTEGRATION_KEYS = [
        SettingKeyEnum::IMS,
        SettingKeyEnum::MOODLE,
        SettingKeyEnum::SPOT_PLAYER,
        SettingKeyEnum::SKYROOM,
        SettingKeyEnum::NILIROOM,
        SettingKeyEnum::SMS_IPPANEL,
    ];

    public function __construct(private readonly CacheStore $cache) {}

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

        // Decrypt secret fields for secret-bearing keys (backward-compatible: plaintext passes through).
        $secretFields = $key->secretFields();

        if (is_array($value) && $key->hasSecrets()) {
            foreach ($secretFields as $field) {
                if (isset($value[$field]) && is_string($value[$field])) {
                    $value[$field] = $this->tryDecrypt($value[$field]);
                }
            }
        }

        // Integration keys store credentials — skip media resolution.
        if (is_array($value) && ! empty($value) && ! in_array($key, self::INTEGRATION_KEYS, true)) {
            return Setting::witImages($value);
        }

        return $value;
    }

    /**
     * Persists a setting value, invalidates the cache, and returns the Setting model.
     */
    public function set(SettingKeyEnum $key, mixed $value, string $type = 'json', ?string $group = null): Setting
    {
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
        $this->cache->forget(CacheKey::Settings);
    }

    /**
     * Creates an audit log entry when a secret-bearing setting is written.
     * Secret fields are redacted — never stored in the log.
     */
    private function auditIntegrationWrite(SettingKeyEnum $key, mixed $value): void
    {
        if ($key->secretFields() === []) {
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
    /**
     * @return Collection<int, Setting>
     */
    private function getAll(): Collection
    {
        return $this->cache->rememberForever(CacheKey::Settings, [], function (): Collection {
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

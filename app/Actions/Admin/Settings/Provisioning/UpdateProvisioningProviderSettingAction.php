<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings\Provisioning;

use App\Data\Admin\Settings\Provisioning\ProvisioningProviderSettingData;
use App\Enums\Provisioning\ProvisioningProviderSettingsEnum;
use App\Services\SettingSecretRedactor;
use App\Services\SettingsService;
use Illuminate\Validation\ValidationException;

final class UpdateProvisioningProviderSettingAction
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly BuildProvisioningProviderSettingAction $buildProviderSetting,
    ) {}

    /**
     * Persist one provider's flat settings and return its read payload.
     *
     * Non-sensitive fields follow full-replace semantics: a field the request
     * omits or clears is reset to its configuration default, so what the panel
     * submits is what the platform stores. A `null` (or omitted, or masked)
     * secret keeps the stored value, a real string overwrites it, and an
     * explicit empty string clears it.
     *
     * Enabling a provider that the schema still reports as unconfigured is
     * rejected with a `422` naming every offending field.
     *
     * @return array{key: string, label: string, state: array{enabled: bool, configured: bool, ready: bool}, schema: array<string, list<array<string, mixed>>>, settings: array<string, mixed>}
     */
    public function handle(ProvisioningProviderSettingsEnum $provider, ProvisioningProviderSettingData $data): array
    {
        $settingKey   = $provider->settingKey();
        $dataClass    = $provider->settingDataClass();
        $secretFields = $settingKey->secretFields();

        $stored = $this->settingsService->get($settingKey);
        $stored = is_array($stored) ? $stored : [];

        $submitted = $data->toArray();

        $settings  = [];
        $effective = [];

        foreach ($provider->defaultConfig() as $key => $default) {
            $value = $submitted[$key] ?? null;

            if (in_array($key, $secretFields, true)) {
                $keepsStored = $value === null || $value === SettingSecretRedactor::REDACTED;

                // Hand the placeholder to SettingsService, which owns the
                // "keep the stored secret" rule; an explicit "" clears it.
                $settings[$key]  = $keepsStored ? SettingSecretRedactor::REDACTED : $value;
                $effective[$key] = $keepsStored ? ($stored[$key] ?? '') : $value;

                continue;
            }

            $resolved        = $value ?? $default;
            $settings[$key]  = $resolved;
            $effective[$key] = $resolved;
        }

        if (($effective['enabled'] ?? false) === true) {
            $this->assertConfiguredWhenEnabled($dataClass, $effective);
        }

        $this->settingsService->set($settingKey, $settings, 'json', 'integrations');

        return $this->buildProviderSetting->handle($provider);
    }

    /**
     * Reject enabling a provider whose required connection fields are empty.
     *
     * The rule comes from the provider's schema, and the message names the field
     * with its translated label, so the error matches the form the panel renders.
     *
     * @param  class-string<ProvisioningProviderSettingData>  $dataClass
     * @param  array<string, mixed>  $effective
     *
     * @throws ValidationException
     */
    private function assertConfiguredWhenEnabled(string $dataClass, array $effective): void
    {
        $errors = [];

        foreach ($dataClass::requiredFields() as $field => $label) {
            if (($effective[$field] ?? null) === null || $effective[$field] === '') {
                $errors[$field] = __('provisioning.errors.enable_requires_field', ['field' => $label]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

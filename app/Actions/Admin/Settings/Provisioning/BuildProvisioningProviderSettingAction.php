<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings\Provisioning;

use App\Enums\Provisioning\ProvisioningProviderSettingsEnum;
use App\Services\SettingSecretRedactor;
use App\Services\SettingsService;

final class BuildProvisioningProviderSettingAction
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly SettingSecretRedactor $redactor,
    ) {}

    /**
     * Resolve one provider into the admin payload: display label, grouped field
     * schema, computed state and effective settings.
     *
     * The stored row wins over the configuration block, unknown stored keys are
     * dropped (only keys the config declares are part of the contract), and the
     * secret is always returned masked. `state.configured` is derived from the
     * provider's own schema rather than from its adapter, so the badge and the
     * form cannot disagree; `state.ready` is that combined with the switch.
     *
     * @return array{key: string, label: string, state: array{enabled: bool, configured: bool, ready: bool}, schema: array<string, list<array<string, mixed>>>, settings: array<string, mixed>}
     */
    public function handle(ProvisioningProviderSettingsEnum $provider): array
    {
        $settingKey = $provider->settingKey();
        $dataClass  = $provider->settingDataClass();
        $defaults   = $provider->defaultConfig();

        $stored = $this->settingsService->get($settingKey);
        $stored = is_array($stored) ? $stored : [];

        $settings   = array_merge($defaults, array_intersect_key($stored, $defaults));
        $enabled    = (bool) ($settings['enabled'] ?? false);
        $configured = $dataClass::isConfigured($settings);

        return [
            'key'   => $provider->value,
            'label' => $provider->label(),
            'state' => [
                'enabled'    => $enabled,
                'configured' => $configured,
                'ready'      => $enabled && $configured,
            ],
            'schema'   => $dataClass::schema(),
            'settings' => $this->redactor->redact($settingKey->value, $settings),
        ];
    }
}

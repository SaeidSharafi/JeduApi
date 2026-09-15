<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings\Sms;

use App\Enums\Sms\SmsGatewayEnum;
use App\Services\SettingSecretRedactor;
use App\Services\SettingsService;

final class BuildSmsGatewaySettingAction
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly SettingSecretRedactor $redactor,
    ) {}

    /**
     * Resolve one gateway into the admin payload: display label, grouped field
     * schema, and effective settings.
     *
     * The stored row wins over the configuration block, unknown stored keys are
     * dropped (only keys the config declares are part of the contract), and the
     * secret is always returned masked.
     *
     * @return array{key: string, label: string, schema: array<string, list<array<string, mixed>>>, settings: array<string, mixed>}
     */
    public function handle(SmsGatewayEnum $gateway): array
    {
        $settingKey = $gateway->settingKey();
        $defaults   = $gateway->defaultConfig();
        $stored     = $this->settingsService->get($settingKey);
        $stored     = is_array($stored) ? $stored : [];

        $settings = array_merge($defaults, array_intersect_key($stored, $defaults));

        // The config default for `label` is a translation key; resolve it only
        // when no label was saved, so a stored label is returned verbatim.
        if (! array_key_exists('label', $stored)) {
            $settings['label'] = __((string) ($settings['label'] ?? ''));
        }

        return [
            'key'      => $gateway->value,
            'label'    => $gateway->label(),
            'schema'   => $gateway->settingDataClass()::schema(),
            'settings' => $this->redactor->redact($settingKey->value, $settings),
        ];
    }
}

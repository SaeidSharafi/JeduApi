<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings\Sms;

use App\Data\Admin\Settings\Sms\SmsGatewaySettingData;
use App\Enums\Sms\SmsGatewayEnum;
use App\Services\SettingSecretRedactor;
use App\Services\SettingsService;
use Illuminate\Validation\ValidationException;

final class UpdateSmsGatewaySettingAction
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly BuildSmsGatewaySettingAction $buildGatewaySetting,
    ) {}

    /**
     * Persist one gateway's settings and return its read payload.
     *
     * A null (or omitted) `api_key` keeps the stored secret, the redacted
     * placeholder keeps it too, a real string overwrites it, and an explicit
     * empty string clears it. Enabling a gateway that would be left without a
     * stored key is rejected on `api_key`; the required sender number is
     * enforced by the request rules.
     *
     * @return array{key: string, label: string, schema: array<string, list<array<string, mixed>>>, settings: array<string, mixed>}
     */
    public function handle(SmsGatewayEnum $gateway, SmsGatewaySettingData $data): array
    {
        $settingKey = $gateway->settingKey();
        $stored     = $this->settingsService->get($settingKey);
        $stored     = is_array($stored) ? $stored : [];

        // A null (or omitted) key and the masked placeholder both mean "keep the
        // stored secret": hand the placeholder to SettingsService, which owns
        // that preservation rule. Only a non-empty string is a new secret.
        $keepsStoredKey = $data->api_key === null || $data->api_key === SettingSecretRedactor::REDACTED;
        $effectiveKey   = $keepsStoredKey ? ($stored['api_key'] ?? null) : $data->api_key;

        if ($data->enabled && ($effectiveKey === null || $effectiveKey === '')) {
            throw ValidationException::withMessages([
                'api_key' => __('sms.errors.enable_requires_api_key'),
            ]);
        }

        $this->settingsService->set($settingKey, [
            'enabled' => $data->enabled,
            'label'   => $data->label,
            'from'    => $data->from,
            'api_key' => $keepsStoredKey ? SettingSecretRedactor::REDACTED : $data->api_key,
            'sandbox' => $data->sandbox,
        ], 'json', 'sms');

        return $this->buildGatewaySetting->handle($gateway);
    }
}

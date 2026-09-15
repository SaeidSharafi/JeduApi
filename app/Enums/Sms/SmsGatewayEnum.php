<?php

declare(strict_types=1);

namespace App\Enums\Sms;

use App\Data\Admin\Settings\Sms\SmsGatewaySettingData;
use App\Enums\System\SettingKeyEnum;

/**
 * The SMS gateways the admin panel can configure.
 *
 * Adding a gateway is a backend-only change: add a case here, point it at its
 * setting data class and config block, and the list endpoint picks it up.
 */
enum SmsGatewayEnum: string
{
    case IPPANEL = 'ippanel';

    /**
     * The setting key this gateway's configuration is persisted under.
     */
    public function settingKey(): SettingKeyEnum
    {
        return match ($this) {
            self::IPPANEL => SettingKeyEnum::SMS_IPPANEL,
        };
    }

    /**
     * The data class that owns this gateway's field schema and request rules.
     *
     * @return class-string<SmsGatewaySettingData>
     */
    public function settingDataClass(): string
    {
        return match ($this) {
            self::IPPANEL => SmsGatewaySettingData::class,
        };
    }

    /**
     * Configuration-derived defaults, used until the gateway is saved.
     *
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return config('sms.gateways.'.$this->value, []);
    }

    /**
     * Merge one stored gateway row over the configuration defaults.
     *
     * The single precedence rule for both the admin read path and the runtime
     * send path: the stored row wins where it declares a field, so a key saved
     * in the panel takes effect without a deployment, while a never-saved
     * gateway still resolves a complete settings array. Stored keys the
     * configuration does not declare are dropped, so only the documented
     * fields are part of the contract. `label` is returned exactly as stored or
     * configured — the caller decides whether to translate it.
     *
     * @return array<string, mixed>
     */
    public function resolvedSettings(mixed $stored): array
    {
        $defaults = $this->defaultConfig();
        $stored   = is_array($stored) ? array_intersect_key($stored, $defaults) : [];

        return array_merge($defaults, $stored);
    }

    /**
     * Translated display label for the admin panel.
     */
    public function label(): string
    {
        return __("sms.gateways.{$this->value}.label");
    }
}

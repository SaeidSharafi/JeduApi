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
     * Translated display label for the admin panel.
     */
    public function label(): string
    {
        return __("sms.gateways.{$this->value}.label");
    }
}

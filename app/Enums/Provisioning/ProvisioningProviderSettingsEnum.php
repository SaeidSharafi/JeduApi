<?php

declare(strict_types=1);

namespace App\Enums\Provisioning;

use App\Data\Admin\Settings\Provisioning\ImsProviderSettingData;
use App\Data\Admin\Settings\Provisioning\MoodleProviderSettingData;
use App\Data\Admin\Settings\Provisioning\ProvisioningProviderSettingData;
use App\Data\Admin\Settings\Provisioning\SpotPlayerProviderSettingData;
use App\Enums\System\SettingKeyEnum;
use App\Services\Integrations\AbstractIntegrationService;
use App\Services\Integrations\ImsService;
use App\Services\Integrations\MoodleService;
use App\Services\Integrations\SpotPlayerService;

/**
 * The provisioning providers the admin panel can configure.
 *
 * This is the settings-facing list, not the persisted
 * {@see \App\Enums\ProvisioningProviderEnum}. BBB is intentionally absent
 * because ADR 0012 keeps it as an environment-configured legacy fallback rather
 * than something the panel manages, and Moodle Quiz has no credentials of its
 * own because it runs on the Moodle configuration — so both, and any unknown
 * key, are `404` through route binding rather than a special case.
 *
 * Adding a provider is a backend-only change: add a case with its setting key,
 * data class (schema + request rules), config defaults and translated label.
 */
enum ProvisioningProviderSettingsEnum: string
{
    case IMS        = 'ims';
    case MOODLE     = 'moodle';
    case SPOTPLAYER = 'spotplayer';

    /**
     * The setting key this provider's configuration is persisted under.
     */
    public function settingKey(): SettingKeyEnum
    {
        return match ($this) {
            self::IMS        => SettingKeyEnum::IMS,
            self::MOODLE     => SettingKeyEnum::MOODLE,
            self::SPOTPLAYER => SettingKeyEnum::SPOT_PLAYER,
        };
    }

    /**
     * The data class that owns this provider's field schema and request rules.
     *
     * @return class-string<ProvisioningProviderSettingData>
     */
    public function settingDataClass(): string
    {
        return match ($this) {
            self::IMS        => ImsProviderSettingData::class,
            self::MOODLE     => MoodleProviderSettingData::class,
            self::SPOTPLAYER => SpotPlayerProviderSettingData::class,
        };
    }

    /**
     * The integration service that consumes this provider's configuration.
     *
     * This is the seam the adapter-agreement test uses to prove the computed
     * state matches the adapter's own configuration check.
     *
     * @return class-string<AbstractIntegrationService>
     */
    public function serviceClass(): string
    {
        return match ($this) {
            self::IMS        => ImsService::class,
            self::MOODLE     => MoodleService::class,
            self::SPOTPLAYER => SpotPlayerService::class,
        };
    }

    /**
     * Configuration-derived defaults, used until the provider is saved.
     *
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return config('provisioning.providers.'.$this->value, []);
    }

    /**
     * Translated display label for the admin panel.
     */
    public function label(): string
    {
        return __("provisioning.providers.{$this->value}.label");
    }
}

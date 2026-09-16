<?php

declare(strict_types=1);

namespace App\Enums\Provisioning;

use App\Data\Admin\Settings\Provisioning\ImsProviderSettingData;
use App\Data\Admin\Settings\Provisioning\MoodleProviderSettingData;
use App\Data\Admin\Settings\Provisioning\NiliroomProviderSettingData;
use App\Data\Admin\Settings\Provisioning\ProvisioningProviderSettingData;
use App\Data\Admin\Settings\Provisioning\SkyroomProviderSettingData;
use App\Data\Admin\Settings\Provisioning\SpotPlayerProviderSettingData;
use App\Enums\System\SettingKeyEnum;
use App\Services\Integrations\AbstractIntegrationService;
use App\Services\Integrations\ImsService;
use App\Services\Integrations\MoodleService;
use App\Services\Integrations\NiliroomService;
use App\Services\Integrations\SkyroomService;
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
 * Niliroom is a live-session integration rather than a provisioning provider
 * (ADR 0007), but it is configured here like the others and its readiness now
 * comes from its adapter as well as its schema.
 *
 * Adding a provider is a backend-only change: add a case with its setting key,
 * data class (schema + request rules), config defaults, translated label and, if
 * it talks to a provider, its adapter.
 */
enum ProvisioningProviderSettingsEnum: string
{
    case IMS        = 'ims';
    case MOODLE     = 'moodle';
    case SPOTPLAYER = 'spotplayer';
    case SKYROOM    = 'skyroom';
    case NILIROOM   = 'niliroom';

    /**
     * The setting key this provider's configuration is persisted under.
     */
    public function settingKey(): SettingKeyEnum
    {
        return match ($this) {
            self::IMS        => SettingKeyEnum::IMS,
            self::MOODLE     => SettingKeyEnum::MOODLE,
            self::SPOTPLAYER => SettingKeyEnum::SPOT_PLAYER,
            self::SKYROOM    => SettingKeyEnum::SKYROOM,
            self::NILIROOM   => SettingKeyEnum::NILIROOM,
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
            self::SKYROOM    => SkyroomProviderSettingData::class,
            self::NILIROOM   => NiliroomProviderSettingData::class,
        };
    }

    /**
     * The integration service that consumes this provider's configuration.
     *
     * This is the seam the adapter-agreement test uses to prove the computed
     * state matches the adapter's own configuration check, so every case the
     * panel lists must name the adapter that reads its settings.
     *
     * @return class-string<AbstractIntegrationService>
     */
    public function serviceClass(): string
    {
        return match ($this) {
            self::IMS        => ImsService::class,
            self::MOODLE     => MoodleService::class,
            self::SPOTPLAYER => SpotPlayerService::class,
            self::SKYROOM    => SkyroomService::class,
            self::NILIROOM   => NiliroomService::class,
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

<?php

declare(strict_types=1);

use App\Actions\Admin\Settings\Provisioning\BuildProvisioningProviderSettingAction;
use App\Data\Admin\Settings\Provisioning\ImsProviderSettingData;
use App\Data\Admin\Settings\Provisioning\MoodleProviderSettingData;
use App\Data\Admin\Settings\Provisioning\NiliroomProviderSettingData;
use App\Data\Admin\Settings\Provisioning\ProvisioningProviderSettingData;
use App\Data\Admin\Settings\Provisioning\SkyroomProviderSettingData;
use App\Data\Admin\Settings\Provisioning\SpotPlayerProviderSettingData;
use App\Enums\Provisioning\ProvisioningProviderSettingsEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Setting;
use App\Services\Integrations\AbstractIntegrationService;
use App\Services\SettingsService;

covers(
    BuildProvisioningProviderSettingAction::class,
    ProvisioningProviderSettingsEnum::class,
    ProvisioningProviderSettingData::class,
    ImsProviderSettingData::class,
    MoodleProviderSettingData::class,
    SpotPlayerProviderSettingData::class,
    SkyroomProviderSettingData::class,
    NiliroomProviderSettingData::class,
);

dataset('provider configurations', ['complete', 'incomplete', 'disabled but complete']);

/**
 * The values that make a provider complete, incomplete or disabled-but-complete.
 *
 * Every provider has its own connection fields, so the required set is declared
 * per provider and then emptied for the incomplete case.
 *
 * @return array<string, mixed>
 */
function providerAgreementValues(ProvisioningProviderSettingsEnum $provider, string $kind): array
{
    $required = match ($provider) {
        ProvisioningProviderSettingsEnum::IMS => [
            'base_url' => 'https://ims.test',
            'api_key'  => 'ims-key',
        ],
        ProvisioningProviderSettingsEnum::MOODLE => [
            'base_url'           => 'https://moodle.test',
            'token'              => 'moodle-service-token',
            'auth_userkey_token' => 'moodle-login-token',
        ],
        ProvisioningProviderSettingsEnum::SPOTPLAYER => [
            'endpoint' => 'https://panel.spotplayer.ir/license/edit/',
            'api_key'  => 'spotplayer-key',
        ],
        ProvisioningProviderSettingsEnum::SKYROOM => [
            'api_key' => 'skyroom-key',
        ],
        ProvisioningProviderSettingsEnum::NILIROOM => [
            'base_url'  => 'https://niliroom.test',
            'api_token' => 'niliroom-token',
        ],
    };

    // The behaviour fields the schema declares, so the stored rows match what
    // the panel would write for providers that have them (Skyroom and Niliroom
    // declare no timeout).
    $behaviour = array_key_exists('timeout', $provider->defaultConfig()) ? ['timeout' => 15] : [];

    return match ($kind) {
        'complete'   => ['enabled' => true]  + $required + $behaviour,
        'incomplete' => ['enabled' => true]  + array_map(static fn (): string => '', $required),
        default      => ['enabled' => false] + $required + $behaviour,
    };
}

/*
|--------------------------------------------------------------------------
| Adapter agreement
|--------------------------------------------------------------------------
|
| The badge the panel shows is computed from the provider schema, while the
| adapters decide for themselves whether they can deliver. These tests are the
| contract between the two: every provider listed in the panel has an adapter,
| and its schema-derived `enabled`/`configured`/`ready` must equal what that
| adapter reports — both for a stored row and for a provider that only has
| `config/provisioning.php` defaults.
|
| `assertConfigured()` is the adapter's public configuration check; it throws
| when the configuration is broken and returns silently when it is usable, so
| it isolates `validateConfig()` from the `enabled` switch.
*/

/**
 * Assert the schema-derived state equals what the provider's adapter reports.
 *
 * @param  array{enabled: bool, configured: bool, ready: bool}  $state
 */
function expectProviderStateToMatchAdapter(ProvisioningProviderSettingsEnum $provider, array $state): void
{
    /** @var AbstractIntegrationService $service */
    $service = app($provider->serviceClass());

    $adapterConfigured = true;

    try {
        $service->assertConfigured();
    } catch (UnrecoverableProvisioningException) {
        $adapterConfigured = false;
    }

    expect([
        'enabled'    => $state['enabled'],
        'configured' => $state['configured'],
        'ready'      => $state['ready'],
    ])->toBe([
        'enabled'    => $service->isEnabled(),
        'configured' => $adapterConfigured,
        'ready'      => $service->isReady(),
    ]);
}

it('agrees with each provider adapter', function (string $kind): void {
    foreach (ProvisioningProviderSettingsEnum::cases() as $provider) {
        Setting::updateOrCreate(
            ['key' => $provider->settingKey()->value],
            ['value' => providerAgreementValues($provider, $kind), 'type' => 'json', 'group' => 'integrations'],
        );
        app(SettingsService::class)->forget();

        expectProviderStateToMatchAdapter(
            $provider,
            app(BuildProvisioningProviderSettingAction::class)->handle($provider)['state'],
        );
    }
})->with('provider configurations');

it('agrees on configuration-only defaults when no provider row is stored', function (): void {
    foreach (ProvisioningProviderSettingsEnum::cases() as $provider) {
        // No stored row: the panel and the adapter must both resolve the same
        // config/provisioning.php block, which is what repointing the adapter
        // fallback path unified.
        config()->set('provisioning.providers.'.$provider->value, array_merge(
            config('provisioning.providers.'.$provider->value, []),
            providerAgreementValues($provider, 'disabled but complete'),
        ));
        app(SettingsService::class)->forget();

        expectProviderStateToMatchAdapter(
            $provider,
            app(BuildProvisioningProviderSettingAction::class)->handle($provider)['state'],
        );
    }
});

/*
|--------------------------------------------------------------------------
| Declaration alignment
|--------------------------------------------------------------------------
|
| A provider is declared three times — its presentation schema, its request
| rules and the config block that defines what the save action persists. They
| are hand-written, so this test is what keeps them from drifting: a key added
| to one list but not the others would otherwise be validated and then dropped,
| or served to the form but never stored.
*/

it('declares the same keys in every provider schema, rules and config block', function (): void {
    foreach (ProvisioningProviderSettingsEnum::cases() as $provider) {
        $dataClass = $provider->settingDataClass();

        $schemaKeys = array_map(
            static fn (array $field): string => (string) $field['key'],
            $dataClass::fields(),
        );
        $ruleKeys   = array_keys($dataClass::getValidationRules([]));
        $storedKeys = array_keys($provider->defaultConfig());

        sort($schemaKeys);
        sort($ruleKeys);
        sort($storedKeys);

        expect($storedKeys)->toBe($schemaKeys)
            ->and($ruleKeys)->toBe($schemaKeys);
    }
});

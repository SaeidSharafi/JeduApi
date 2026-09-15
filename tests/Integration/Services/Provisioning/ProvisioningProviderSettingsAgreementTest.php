<?php

declare(strict_types=1);

use App\Actions\Admin\Settings\Provisioning\BuildProvisioningProviderSettingAction;
use App\Data\Admin\Settings\Provisioning\ImsProviderSettingData;
use App\Data\Admin\Settings\Provisioning\ProvisioningProviderSettingData;
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
);

dataset('provider configurations', [
    'complete' => [[
        'enabled'  => true,
        'base_url' => 'https://ims.test',
        'api_key'  => 'ims-key',
        'timeout'  => 15,
    ]],
    'incomplete' => [[
        'enabled'  => true,
        'base_url' => '',
        'api_key'  => '',
    ]],
    'disabled but complete' => [[
        'enabled'  => false,
        'base_url' => 'https://ims.test',
        'api_key'  => 'ims-key',
        'timeout'  => 15,
    ]],
]);

/*
|--------------------------------------------------------------------------
| Adapter agreement
|--------------------------------------------------------------------------
|
| The badge the panel shows is computed from the provider schema, while the
| adapters decide for themselves whether they can deliver. This test is the
| contract between the two: for every provider that has an adapter, the
| schema-derived `enabled`/`configured`/`ready` must equal what the adapter
| reports for a complete, an incomplete and a disabled-but-complete provider.
|
| `assertConfigured()` is the adapter's public configuration check; it throws
| when the configuration is broken and returns silently when it is usable, so
| it isolates `validateConfig()` from the `enabled` switch.
*/

it('agrees with each provider adapter', function (array $values): void {
    foreach (ProvisioningProviderSettingsEnum::cases() as $provider) {
        Setting::updateOrCreate(
            ['key' => $provider->settingKey()->value],
            ['value' => $values, 'type' => 'json', 'group' => 'integrations'],
        );
        app(SettingsService::class)->forget();

        $state = app(BuildProvisioningProviderSettingAction::class)->handle($provider)['state'];

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
})->with('provider configurations');

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

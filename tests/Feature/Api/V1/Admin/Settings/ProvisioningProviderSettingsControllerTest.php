<?php

declare(strict_types=1);

use App\Actions\Admin\Settings\Provisioning\BuildProvisioningProviderSettingAction;
use App\Actions\Admin\Settings\Provisioning\UpdateProvisioningProviderSettingAction;
use App\Data\Admin\Settings\Provisioning\ImsProviderSettingData;
use App\Data\Admin\Settings\Provisioning\ProvisioningProviderSettingData;
use App\Enums\PermissionEnum;
use App\Enums\Provisioning\ProvisioningProviderSettingsEnum;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Api\Admin\Settings\ProvisioningProviderSettingsController;
use App\Models\Setting;
use App\Services\SettingSecretRedactor;
use Illuminate\Support\Facades\Crypt;

covers(
    ProvisioningProviderSettingsController::class,
    BuildProvisioningProviderSettingAction::class,
    UpdateProvisioningProviderSettingAction::class,
    ProvisioningProviderSettingsEnum::class,
    ProvisioningProviderSettingData::class,
    ImsProviderSettingData::class,
);

function provisioningProviderUrl(string $name, array $parameters = []): string
{
    return route("api.v1.admin.settings.provisioning-providers.{$name}", $parameters);
}

/*
|--------------------------------------------------------------------------
| Mutation notes
|--------------------------------------------------------------------------
|
| Survivors left after `pest --mutate` for this file, and why:
|
| - `BuildProvisioningProviderSettingAction` `(bool)` cast on `enabled`: the
|   config block casts every `enabled` with `(bool)` and the save action writes a
|   boolean, so the cast cannot change a served value.
| - `?? false` on `enabled` in both actions: the settings map is built by
|   iterating `defaultConfig()`, which always declares `enabled`, so the fallback
|   is unreachable.
| - `ImsProviderSettingData::rules()` dropping the whole `api_key` entry: an
|   absent entry makes spatie/laravel-data derive an equivalent `nullable|string`
|   rule from the typed `?string $api_key` property, so the `422` is unchanged.
| - `ProvisioningProviderSettingData::requiredFields()` `&&` → `||`: IMS has no
|   optional non-boolean field with an empty default (`timeout` defaults to 15),
|   so widening "required for readiness" to every non-boolean field cannot change
|   the computed `state`. It becomes observable once a provider has an optional
|   field that can be empty (#104/#105).
| - Pest also reports a varying number of mutants as timeouts (4 to 6 across runs
|   of identical production code). The changed code has no unbounded loop or
|   recursion, so those are the shared 12-process mutation runner exceeding its
|   per-mutation budget under load, not uncovered behaviour.
*/

describe('index', function (): void {
    it('returns the ims provider with its grouped schema, state and configuration defaults', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        app()->setLocale('fa');

        $response = $this->getJson(provisioningProviderUrl('index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', ProvisioningProviderSettingsEnum::IMS->value)
            ->assertJsonPath('data.0.label', 'IMS')
            ->assertJsonPath('data.0.state', ['enabled' => false, 'configured' => false, 'ready' => false])
            ->assertJsonPath('data.0.settings', [
                'enabled'  => false,
                'base_url' => null,
                'api_key'  => SettingSecretRedactor::REDACTED,
                'timeout'  => 15,
            ]);

        expect($response->json('data.0.schema'))->toBe([
            'general' => [
                ['key' => 'enabled', 'type' => 'boolean', 'label' => 'فعال', 'required' => true, 'default' => false],
            ],
            'connection' => [
                ['key' => 'base_url', 'type' => 'url', 'label' => 'آدرس سرویس', 'required' => true],
            ],
            'credentials' => [
                ['key' => 'api_key', 'type' => 'password', 'label' => 'کلید API', 'required' => true, 'sensitive' => true],
            ],
            'advanced' => [
                ['key' => 'timeout', 'type' => 'number', 'label' => 'مهلت (ثانیه)', 'required' => false, 'default' => 15],
            ],
        ]);
    });

    it('never exposes a storage key on a provider item', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);

        $response = $this->getJson(provisioningProviderUrl('index'));

        $response->assertOk();

        expect(array_keys($response->json('data.0')))->toBe(['key', 'label', 'state', 'schema', 'settings']);
    });

    it('returns stored provider values over the configuration defaults', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        Setting::factory()->imsSecondary()->create();

        $response = $this->getJson(provisioningProviderUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.0.state', ['enabled' => true, 'configured' => true, 'ready' => true])
            ->assertJsonPath('data.0.settings.base_url', 'https://stored-ims.example.com')
            ->assertJsonPath('data.0.settings.timeout', 30)
            ->assertJsonPath('data.0.settings.api_key', SettingSecretRedactor::REDACTED);
    });

    it('reports an enabled provider without its secret as not configured and not ready', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        Setting::factory()->ims()->create([
            'value' => [
                'enabled'  => true,
                'base_url' => 'https://ims.example.com',
            ],
        ]);

        $response = $this->getJson(provisioningProviderUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.0.state', ['enabled' => true, 'configured' => false, 'ready' => false]);
    });

    it('reports a provider with a cleared secret as not configured', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        Setting::factory()->ims()->create([
            'value' => [
                'enabled'  => false,
                'base_url' => 'https://ims.example.com',
                'api_key'  => '',
            ],
        ]);

        $response = $this->getJson(provisioningProviderUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.0.state', ['enabled' => false, 'configured' => false, 'ready' => false]);
    });

    it('drops unknown stored keys from the returned settings', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        Setting::factory()->imsSecondary()->create();

        $response = $this->getJson(provisioningProviderUrl('index'));

        $response->assertOk();

        expect($response->json('data.0.settings'))->not->toHaveKey('create_studets');
    });

    it('returns 401 when unauthenticated', function (): void {
        $this->getJson(provisioningProviderUrl('index'))->assertUnauthorized();
    });

    it('returns 403 without the settings view permission', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->getJson(provisioningProviderUrl('index'))->assertForbidden();
    });
});

describe('show', function (): void {
    it('returns a single provider item shaped like a list entry', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);

        $response = $this->getJson(provisioningProviderUrl('show', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['key', 'label', 'state', 'schema', 'settings'],
            ])
            ->assertJsonPath('data.key', ProvisioningProviderSettingsEnum::IMS->value);
    });

    it('returns 404 for a provider that is not offered', function (string $provider): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);

        $this->getJson(provisioningProviderUrl('show', ['provider' => $provider]))->assertNotFound();
    })->with(['bbb', 'moodle_quiz', 'unknown-provider']);

    it('returns 403 without the settings view permission', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->getJson(provisioningProviderUrl('show', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]))->assertForbidden();
    });

    it('returns 401 when unauthenticated', function (): void {
        $this->getJson(provisioningProviderUrl('show', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]))->assertUnauthorized();
    });
});

describe('update', function (): void {
    it('stores the submitted flat settings and returns the masked provider item', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $response = $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => true,
            'base_url' => 'https://ims.test',
            'api_key'  => 'fresh-key',
            'timeout'  => 30,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.key', ProvisioningProviderSettingsEnum::IMS->value)
            ->assertJsonPath('data.state', ['enabled' => true, 'configured' => true, 'ready' => true])
            ->assertJsonPath('data.settings.base_url', 'https://ims.test')
            ->assertJsonPath('data.settings.timeout', 30)
            ->assertJsonPath('data.settings.api_key', SettingSecretRedactor::REDACTED);

        $stored = Setting::where('key', SettingKeyEnum::IMS->value)->firstOrFail();

        expect($stored->group)->toBe('integrations')
            ->and(Crypt::decryptString($stored->value['api_key']))->toBe('fresh-key');
    });

    it('keeps the stored api key when the field is omitted', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->imsSecondary()->create();

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => true,
            'base_url' => 'https://stored-ims.example.com',
        ])->assertOk()
            ->assertJsonPath('data.settings.api_key', SettingSecretRedactor::REDACTED);

        $stored = Setting::where('key', SettingKeyEnum::IMS->value)->firstOrFail();

        expect($stored->value['api_key'])->toBe('stored-ims-key');
    });

    it('keeps the stored api key when the field is null', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->imsSecondary()->create();

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => true,
            'base_url' => 'https://stored-ims.example.com',
            'api_key'  => null,
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::IMS->value)->firstOrFail();

        expect($stored->value['api_key'])->toBe('stored-ims-key');
    });

    it('keeps the stored api key when the redacted placeholder is sent back', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->imsSecondary()->create();

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => true,
            'base_url' => 'https://stored-ims.example.com',
            'api_key'  => SettingSecretRedactor::REDACTED,
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::IMS->value)->firstOrFail();

        expect($stored->value['api_key'])->toBe('stored-ims-key');
    });

    it('clears the stored api key on an explicit empty value', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->imsSecondary()->create();

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => false,
            'base_url' => 'https://stored-ims.example.com',
            'api_key'  => '',
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::IMS->value)->firstOrFail();

        expect($stored->value['api_key'])->toBe('');
    });

    it('saves a disabled provider with empty connection fields', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $response = $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => false,
            'base_url' => '',
            'api_key'  => '',
            'timeout'  => '',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.state', ['enabled' => false, 'configured' => false, 'ready' => false])
            ->assertJsonPath('data.settings.base_url', null)
            ->assertJsonPath('data.settings.timeout', 15)
            ->assertJsonPath('data.settings.api_key', SettingSecretRedactor::REDACTED);

        $stored = Setting::where('key', SettingKeyEnum::IMS->value)->firstOrFail();

        expect($stored->value)->toBe([
            'enabled'  => false,
            'base_url' => null,
            'api_key'  => '',
            'timeout'  => 15,
        ]);
    });

    it('rejects enabling the provider without a base url or an api key', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        app()->setLocale('fa');

        $response = $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => true,
            'base_url' => null,
            'api_key'  => null,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['base_url', 'api_key'])
            ->assertJsonPath('errors.base_url.0', __('provisioning.errors.enable_requires_field', ['field' => 'آدرس سرویس']))
            ->assertJsonPath('errors.api_key.0', __('provisioning.errors.enable_requires_field', ['field' => 'کلید API']));

        expect(Setting::where('key', SettingKeyEnum::IMS->value)->exists())->toBeFalse();
    });

    it('rejects enabling the provider without an api key when a base url is present', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => true,
            'base_url' => 'https://ims.test',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['api_key']);
    });

    it('rejects enabling the provider when the stored api key is cleared', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->imsSecondary()->create();

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => true,
            'base_url' => 'https://stored-ims.example.com',
            'api_key'  => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['api_key']);
    });

    it('enables the provider with an omitted api key when a key is already stored', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->imsSecondary()->create();

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => true,
            'base_url' => 'https://stored-ims.example.com',
        ])->assertOk()
            ->assertJsonPath('data.state', ['enabled' => true, 'configured' => true, 'ready' => true]);
    });

    it('rejects a save without the enabled switch', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'base_url' => 'https://ims.test',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['enabled']);
    });

    it('rejects a non boolean enabled switch', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => 'yes',
            'base_url' => 'https://ims.test',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['enabled']);
    });

    it('rejects a non string api key', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled' => false,
            'api_key' => ['nested'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['api_key']);
    });

    it('rejects a base url that is not a url', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'  => false,
            'base_url' => 'not-a-url',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['base_url']);
    });

    it('rejects a non numeric timeout', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled' => false,
            'timeout' => 'soon',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['timeout']);
    });

    it('rejects a timeout below one second', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled' => false,
            'timeout' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['timeout']);
    });

    it('ignores unknown body keys and does not persist them', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled'        => false,
            'create_studets' => true,
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::IMS->value)->firstOrFail();

        expect($stored->value)->toHaveKeys(['enabled', 'base_url', 'api_key', 'timeout'])
            ->and($stored->value)->not->toHaveKey('create_studets');
    });

    it('returns 404 for a provider that is not offered', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => 'moodle_quiz']), [
            'enabled' => false,
        ])->assertNotFound();
    });

    it('returns 403 without the settings update permission', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled' => false,
        ])->assertForbidden();
    });

    it('returns 401 when unauthenticated', function (): void {
        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::IMS->value]), [
            'enabled' => false,
        ])->assertUnauthorized();
    });
});

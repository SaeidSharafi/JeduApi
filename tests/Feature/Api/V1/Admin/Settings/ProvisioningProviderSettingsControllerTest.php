<?php

declare(strict_types=1);

use App\Actions\Admin\Settings\Provisioning\BuildProvisioningProviderSettingAction;
use App\Actions\Admin\Settings\Provisioning\UpdateProvisioningProviderSettingAction;
use App\Data\Admin\Settings\Provisioning\ImsProviderSettingData;
use App\Data\Admin\Settings\Provisioning\MoodleProviderSettingData;
use App\Data\Admin\Settings\Provisioning\NiliroomProviderSettingData;
use App\Data\Admin\Settings\Provisioning\ProvisioningProviderSettingData;
use App\Data\Admin\Settings\Provisioning\SkyroomProviderSettingData;
use App\Data\Admin\Settings\Provisioning\SpotPlayerProviderSettingData;
use App\Enums\PermissionEnum;
use App\Enums\Provisioning\ProvisioningProviderSettingsEnum;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Api\Admin\Settings\ProvisioningProviderSettingsController;
use App\Models\Setting;
use App\Services\SettingSecretRedactor;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Crypt;

covers(
    ProvisioningProviderSettingsController::class,
    BuildProvisioningProviderSettingAction::class,
    UpdateProvisioningProviderSettingAction::class,
    ProvisioningProviderSettingsEnum::class,
    ProvisioningProviderSettingData::class,
    ImsProviderSettingData::class,
    MoodleProviderSettingData::class,
    SpotPlayerProviderSettingData::class,
    SkyroomProviderSettingData::class,
    NiliroomProviderSettingData::class,
);

function provisioningProviderUrl(string $name, array $parameters = []): string
{
    return route("api.v1.admin.settings.provisioning-providers.{$name}", $parameters);
}

/**
 * A valid save payload for one provider, so a validation test only has to state
 * the field under test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function provisioningProviderPayload(string $provider, array $overrides = []): array
{
    $base = match ($provider) {
        ProvisioningProviderSettingsEnum::IMS->value => [
            'enabled'  => true,
            'base_url' => 'https://ims.test',
            'api_key'  => 'ims-key',
        ],
        ProvisioningProviderSettingsEnum::MOODLE->value => [
            'enabled'            => true,
            'base_url'           => 'https://moodle.test',
            'token'              => 'service-token',
            'auth_userkey_token' => 'login-token',
        ],
        ProvisioningProviderSettingsEnum::SPOTPLAYER->value => [
            'enabled'  => true,
            'endpoint' => 'https://spotplayer.test/license/edit/',
            'api_key'  => 'spot-key',
        ],
        ProvisioningProviderSettingsEnum::SKYROOM->value => [
            'enabled' => true,
            'api_key' => 'skyroom-key',
        ],
        ProvisioningProviderSettingsEnum::NILIROOM->value => [
            'enabled'   => true,
            'base_url'  => 'https://niliroom.test',
            'api_token' => 'niliroom-token',
        ],
    };

    return array_merge($base, $overrides);
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
| - `ProvisioningProviderSettingData::requiredFields()` (confirmed with a
|   `--class`-scoped run): every schema field declares `required` explicitly, so
|   `?? false` / `?? true` are unreachable and dropping the coalesce changes
|   nothing; widening `required && type !== boolean` to `||` only adds fields whose
|   resolved defaults are non-empty (`timeout` 15, `default_role_id` 5, `/my/`, the
|   endpoint URL, and `sandbox` false, which the emptiness check counts as
|   present), so the computed state cannot change; and every label is already a
|   string, so dropping the `(string)` cast is a no-op.
| - Dropping a whole rule entry from a provider's `rules()` when that entry is
|   exactly the rule spatie/laravel-data derives from the typed property —
|   `bool` → `required|boolean`, `?string` → `nullable|string`, `?bool` →
|   `nullable|boolean`. Every other entry is load-bearing and killed: dropping
|   `base_url`/`endpoint` loses `url` (caught by the url dataset) and dropping a
|   `?int` entry loses `min:1` (caught by the minimum dataset). Verified
|   empirically by removing the `api_key` entry and re-running the non-string
|   dataset.
| - `ProvisioningProviderSettingData::normalizePayload()` `continue` → `break`:
|   in every provider the sensitive fields sit last inside `connection`, after
|   which only further sensitive fields follow, so breaking out of the walk skips
|   nothing the sensitive guard would not skip anyway.
| - Pest also reports a varying number of mutants as timeouts (0 to 24 across runs
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
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.key', ProvisioningProviderSettingsEnum::IMS->value)
            ->assertJsonPath('data.0.label', 'IMS')
            ->assertJsonPath('data.0.state', ['enabled' => false, 'configured' => false, 'ready' => false])
            ->assertJsonPath('data.0.settings', [
                'enabled'  => false,
                'base_url' => null,
                'api_key'  => SettingSecretRedactor::REDACTED,
                'timeout'  => 15,
            ]);

        expect(array_column($response->json('data'), 'key'))->toBe([
            ProvisioningProviderSettingsEnum::IMS->value,
            ProvisioningProviderSettingsEnum::MOODLE->value,
            ProvisioningProviderSettingsEnum::SPOTPLAYER->value,
            ProvisioningProviderSettingsEnum::SKYROOM->value,
            ProvisioningProviderSettingsEnum::NILIROOM->value,
        ]);

        expect($response->json('data.0.schema'))->toBe([
            'general' => [
                ['key' => 'enabled', 'type' => 'boolean', 'label' => 'فعال', 'required' => true, 'default' => false],
                ['key' => 'timeout', 'type' => 'number', 'label' => 'مهلت (ثانیه)', 'required' => false, 'default' => 15],
            ],
            'connection' => [
                ['key' => 'base_url', 'type' => 'url', 'label' => 'آدرس سرویس', 'required' => true],
                ['key' => 'api_key', 'type' => 'password', 'label' => 'کلید API', 'required' => true, 'sensitive' => true],
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

    it('returns the moodle provider with both tokens required and sensitive', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        app()->setLocale('fa');

        $response = $this->getJson(provisioningProviderUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.1.key', ProvisioningProviderSettingsEnum::MOODLE->value)
            ->assertJsonPath('data.1.label', 'مودل')
            ->assertJsonPath('data.1.state', ['enabled' => false, 'configured' => false, 'ready' => false])
            ->assertJsonPath('data.1.settings', [
                'enabled'                       => false,
                'base_url'                      => null,
                'token'                         => SettingSecretRedactor::REDACTED,
                'auth_userkey_token'            => SettingSecretRedactor::REDACTED,
                'default_role_id'               => 5,
                'default_login_redirect_script' => '/my/',
                'timeout'                       => 15,
            ]);

        expect($response->json('data.1.schema'))->toBe([
            'general' => [
                ['key' => 'enabled', 'type' => 'boolean', 'label' => 'فعال', 'required' => true, 'default' => false],
                ['key' => 'default_role_id', 'type' => 'number', 'label' => 'شناسه نقش پیش‌فرض', 'required' => false, 'default' => 5],
                ['key' => 'default_login_redirect_script', 'type' => 'text', 'label' => 'مسیر بازگشت پس از ورود', 'required' => false, 'default' => '/my/'],
                ['key' => 'timeout', 'type' => 'number', 'label' => 'مهلت (ثانیه)', 'required' => false, 'default' => 15],
            ],
            'connection' => [
                ['key' => 'base_url', 'type' => 'url', 'label' => 'آدرس سرویس', 'required' => true],
                ['key' => 'token', 'type' => 'password', 'label' => 'توکن سرویس', 'required' => true, 'sensitive' => true],
                ['key' => 'auth_userkey_token', 'type' => 'password', 'label' => 'توکن ورود', 'required' => true, 'sensitive' => true],
            ],
        ]);
    });

    it('returns the spotplayer provider with its endpoint key rather than a base url', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        app()->setLocale('fa');

        $response = $this->getJson(provisioningProviderUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.2.key', ProvisioningProviderSettingsEnum::SPOTPLAYER->value)
            ->assertJsonPath('data.2.label', 'اسپات‌پلیر')
            ->assertJsonPath('data.2.state', ['enabled' => false, 'configured' => false, 'ready' => false])
            ->assertJsonPath('data.2.settings', [
                'enabled'  => false,
                'endpoint' => 'https://panel.spotplayer.ir/license/edit/',
                'api_key'  => SettingSecretRedactor::REDACTED,
                'sandbox'  => false,
                'timeout'  => 15,
            ]);

        expect($response->json('data.2.schema'))->toBe([
            'general' => [
                ['key' => 'enabled', 'type' => 'boolean', 'label' => 'فعال', 'required' => true, 'default' => false],
                ['key' => 'sandbox', 'type' => 'boolean', 'label' => 'حالت آزمایشی', 'required' => false, 'default' => false],
                ['key' => 'timeout', 'type' => 'number', 'label' => 'مهلت (ثانیه)', 'required' => false, 'default' => 15],
            ],
            'connection' => [
                ['key' => 'endpoint', 'type' => 'url', 'label' => 'آدرس سرویس', 'required' => true],
                ['key' => 'api_key', 'type' => 'password', 'label' => 'کلید API', 'required' => true, 'sensitive' => true],
            ],
        ]);

        expect($response->json('data.2.settings'))->not->toHaveKey('base_url');
    });

    it('returns the skyroom provider with an optional base url and a required sensitive key', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        app()->setLocale('fa');

        $response = $this->getJson(provisioningProviderUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.3.key', ProvisioningProviderSettingsEnum::SKYROOM->value)
            ->assertJsonPath('data.3.label', 'اسکای‌روم')
            ->assertJsonPath('data.3.state', ['enabled' => false, 'configured' => false, 'ready' => false])
            ->assertJsonPath('data.3.settings', [
                'enabled'  => false,
                'base_url' => 'https://www.skyroom.online/skyroom/api',
                'api_key'  => SettingSecretRedactor::REDACTED,
            ]);

        expect($response->json('data.3.schema'))->toBe([
            'general' => [
                ['key' => 'enabled', 'type' => 'boolean', 'label' => 'فعال', 'required' => true, 'default' => false],
            ],
            'connection' => [
                ['key' => 'base_url', 'type' => 'url', 'label' => 'آدرس سرویس', 'required' => false],
                ['key' => 'api_key', 'type' => 'password', 'label' => 'کلید API', 'required' => true, 'sensitive' => true],
            ],
        ]);
    });

    it('returns the niliroom provider with its api token, resolving values from configuration', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        app()->setLocale('fa');
        config()->set('provisioning.providers.niliroom.base_url', 'https://niliroom.test');
        config()->set('provisioning.providers.niliroom.api_token', 'configured-token');

        $response = $this->getJson(provisioningProviderUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.4.key', ProvisioningProviderSettingsEnum::NILIROOM->value)
            ->assertJsonPath('data.4.label', 'نیلی‌روم')
            ->assertJsonPath('data.4.state', ['enabled' => false, 'configured' => true, 'ready' => false])
            ->assertJsonPath('data.4.settings', [
                'enabled'   => false,
                'base_url'  => 'https://niliroom.test',
                'api_token' => SettingSecretRedactor::REDACTED,
            ]);

        expect($response->json('data.4.schema'))->toBe([
            'general' => [
                ['key' => 'enabled', 'type' => 'boolean', 'label' => 'فعال', 'required' => true, 'default' => false],
            ],
            'connection' => [
                ['key' => 'base_url', 'type' => 'url', 'label' => 'آدرس سرویس', 'required' => true],
                ['key' => 'api_token', 'type' => 'password', 'label' => 'توکن API', 'required' => true, 'sensitive' => true],
            ],
        ]);
    });

    it('resolves spot player defaults from the seeded row and the provisioning config', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        $this->seed(SettingsSeeder::class);

        $seeded = Setting::where('key', SettingKeyEnum::SPOT_PLAYER->value)->firstOrFail()->value;

        $this->getJson(provisioningProviderUrl('show', ['provider' => ProvisioningProviderSettingsEnum::SPOTPLAYER->value]))
            ->assertOk()
            ->assertJsonPath('data.settings.enabled', $seeded['enabled'])
            ->assertJsonPath('data.settings.endpoint', $seeded['endpoint'])
            ->assertJsonPath('data.settings.sandbox', $seeded['sandbox'])
            ->assertJsonPath('data.settings.timeout', 15)
            ->assertJsonPath('data.settings.api_key', SettingSecretRedactor::REDACTED);
    });

    it('reports moodle as not configured when a required field is missing', function (array $overrides): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        Setting::factory()->moodle()->create([
            'value' => array_merge([
                'enabled'            => true,
                'base_url'           => 'https://moodle.example.com',
                'token'              => 'service-token',
                'auth_userkey_token' => 'login-token',
            ], $overrides),
        ]);

        $this->getJson(provisioningProviderUrl('index'))
            ->assertOk()
            ->assertJsonPath('data.1.state', ['enabled' => true, 'configured' => false, 'ready' => false]);
    })->with([
        'missing base url'      => [['base_url' => '']],
        'missing service token' => [['token' => '']],
        'missing login token'   => [['auth_userkey_token' => '']],
    ]);

    it('masks every stored provider secret on read', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        Setting::factory()->moodle()->create();
        Setting::factory()->spotPlayer()->create();
        Setting::factory()->skyroom()->create();
        Setting::factory()->niliroom()->create();

        $this->getJson(provisioningProviderUrl('index'))
            ->assertOk()
            ->assertJsonPath('data.1.settings.token', SettingSecretRedactor::REDACTED)
            ->assertJsonPath('data.1.settings.auth_userkey_token', SettingSecretRedactor::REDACTED)
            ->assertJsonPath('data.2.settings.api_key', SettingSecretRedactor::REDACTED)
            ->assertJsonPath('data.2.settings.endpoint', 'https://panel.spotplayer.ir/license/edit/')
            ->assertJsonPath('data.3.settings.api_key', SettingSecretRedactor::REDACTED)
            ->assertJsonPath('data.3.settings.base_url', 'https://skyroom.example.com')
            ->assertJsonPath('data.4.settings.api_token', SettingSecretRedactor::REDACTED)
            ->assertJsonPath('data.4.settings.base_url', 'https://niliroom.example.com');
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

    it('falls back to the ims defaults when a disabled provider clears its connection fields', function (): void {
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

    it('saves a disabled provider with empty connection fields', function (string $provider): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $dataClass = ProvisioningProviderSettingsEnum::from($provider)->settingDataClass();
        $payload   = ['enabled' => false];

        foreach ($dataClass::fields() as $field) {
            if ($field['key'] !== 'enabled') {
                $payload[$field['key']] = null;
            }
        }

        $this->putJson(provisioningProviderUrl('update', ['provider' => $provider]), $payload)
            ->assertOk()
            ->assertJsonPath('data.state.enabled', false)
            ->assertJsonPath('data.state.ready', false);
    })->with(['ims', 'moodle', 'spotplayer', 'skyroom', 'niliroom']);

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

    it('rejects a save without the enabled switch', function (string $provider): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $payload = provisioningProviderPayload($provider);
        unset($payload['enabled']);

        $this->putJson(provisioningProviderUrl('update', ['provider' => $provider]), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['enabled']);
    })->with(['ims', 'moodle', 'spotplayer', 'skyroom', 'niliroom']);

    it('rejects a non boolean switch', function (string $provider, string $field): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => $provider]), provisioningProviderPayload($provider, [$field => 'yes']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        ['ims', 'enabled'],
        ['moodle', 'enabled'],
        ['spotplayer', 'enabled'],
        ['spotplayer', 'sandbox'],
        ['skyroom', 'enabled'],
        ['niliroom', 'enabled'],
    ]);

    it('rejects a non string field', function (string $provider, string $field): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => $provider]), provisioningProviderPayload($provider, [$field => ['nested']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        ['ims', 'api_key'],
        ['moodle', 'token'],
        ['moodle', 'auth_userkey_token'],
        ['moodle', 'default_login_redirect_script'],
        ['spotplayer', 'api_key'],
        ['skyroom', 'api_key'],
        ['niliroom', 'api_token'],
    ]);

    it('rejects a service url that is not a url', function (string $provider, string $field): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => $provider]), provisioningProviderPayload($provider, [$field => 'not-a-url']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        ['ims', 'base_url'],
        ['moodle', 'base_url'],
        ['spotplayer', 'endpoint'],
        ['skyroom', 'base_url'],
        ['niliroom', 'base_url'],
    ]);

    it('rejects a non numeric field', function (string $provider, string $field): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => $provider]), provisioningProviderPayload($provider, [$field => 'soon']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        ['ims', 'timeout'],
        ['moodle', 'timeout'],
        ['moodle', 'default_role_id'],
        ['spotplayer', 'timeout'],
    ]);

    it('rejects a numeric field below its minimum', function (string $provider, string $field): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => $provider]), provisioningProviderPayload($provider, [$field => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        ['ims', 'timeout'],
        ['moodle', 'timeout'],
        ['moodle', 'default_role_id'],
        ['spotplayer', 'timeout'],
    ]);

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

    it('stores the moodle fields and encrypts both tokens', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $response = $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::MOODLE->value]), [
            'enabled'                       => true,
            'base_url'                      => 'https://moodle.test',
            'token'                         => 'service-token',
            'auth_userkey_token'            => 'login-token',
            'default_role_id'               => 7,
            'default_login_redirect_script' => '/dashboard/',
            'timeout'                       => 30,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.key', ProvisioningProviderSettingsEnum::MOODLE->value)
            ->assertJsonPath('data.state', ['enabled' => true, 'configured' => true, 'ready' => true])
            ->assertJsonPath('data.settings.token', SettingSecretRedactor::REDACTED)
            ->assertJsonPath('data.settings.auth_userkey_token', SettingSecretRedactor::REDACTED)
            ->assertJsonPath('data.settings.default_role_id', 7)
            ->assertJsonPath('data.settings.default_login_redirect_script', '/dashboard/')
            ->assertJsonPath('data.settings.timeout', 30);

        $stored = Setting::where('key', SettingKeyEnum::MOODLE->value)->firstOrFail();

        expect(Crypt::decryptString($stored->value['token']))->toBe('service-token')
            ->and(Crypt::decryptString($stored->value['auth_userkey_token']))->toBe('login-token');
    });

    it('keeps both stored moodle tokens when they are omitted', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->moodle()->create();

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::MOODLE->value]), [
            'enabled'  => true,
            'base_url' => 'https://moodle.example.com',
        ])->assertOk()
            ->assertJsonPath('data.state', ['enabled' => true, 'configured' => true, 'ready' => true])
            ->assertJsonPath('data.settings.token', SettingSecretRedactor::REDACTED)
            ->assertJsonPath('data.settings.auth_userkey_token', SettingSecretRedactor::REDACTED);

        $stored = Setting::where('key', SettingKeyEnum::MOODLE->value)->firstOrFail();

        expect($stored->value['token'])->toBe('moodle-token-secret')
            ->and($stored->value['auth_userkey_token'])->toBe('moodle-userkey-secret');
    });

    it('rejects enabling moodle when a required field is missing', function (array $payload, string $field): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::MOODLE->value]), array_merge([
            'enabled'            => true,
            'base_url'           => 'https://moodle.test',
            'token'              => 'service-token',
            'auth_userkey_token' => 'login-token',
        ], $payload))->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        expect(Setting::where('key', SettingKeyEnum::MOODLE->value)->exists())->toBeFalse();
    })->with([
        'missing base url'      => [['base_url' => null], 'base_url'],
        'missing service token' => [['token' => null], 'token'],
        'missing login token'   => [['auth_userkey_token' => null], 'auth_userkey_token'],
    ]);

    it('stores the spotplayer endpoint, sandbox and timeout and masks its key', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $response = $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::SPOTPLAYER->value]), [
            'enabled'  => true,
            'endpoint' => 'https://spotplayer.test/license/edit/',
            'api_key'  => 'fresh-spot-key',
            'sandbox'  => true,
            'timeout'  => 20,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.key', ProvisioningProviderSettingsEnum::SPOTPLAYER->value)
            ->assertJsonPath('data.state', ['enabled' => true, 'configured' => true, 'ready' => true])
            ->assertJsonPath('data.settings.endpoint', 'https://spotplayer.test/license/edit/')
            ->assertJsonPath('data.settings.sandbox', true)
            ->assertJsonPath('data.settings.timeout', 20)
            ->assertJsonPath('data.settings.api_key', SettingSecretRedactor::REDACTED);

        $stored = Setting::where('key', SettingKeyEnum::SPOT_PLAYER->value)->firstOrFail();

        expect(Crypt::decryptString($stored->value['api_key']))->toBe('fresh-spot-key');
    });

    it('keeps the stored spotplayer key when it is omitted', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->spotPlayer()->create();

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::SPOTPLAYER->value]), [
            'enabled'  => true,
            'endpoint' => 'https://panel.spotplayer.ir/license/edit/',
        ])->assertOk()
            ->assertJsonPath('data.settings.api_key', SettingSecretRedactor::REDACTED);

        $stored = Setting::where('key', SettingKeyEnum::SPOT_PLAYER->value)->firstOrFail();

        expect($stored->value['api_key'])->toBe('spotplayer-api-key-secret');
    });

    it('rejects enabling spotplayer without its endpoint or key', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        config()->set('provisioning.providers.spotplayer.endpoint', null);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::SPOTPLAYER->value]), [
            'enabled' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'api_key']);
    });

    it('falls back to the shipped spotplayer endpoint when the field is omitted', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::SPOTPLAYER->value]), [
            'enabled' => true,
            'api_key' => 'spot-key',
        ])->assertOk()
            ->assertJsonPath('data.state', ['enabled' => true, 'configured' => true, 'ready' => true])
            ->assertJsonPath('data.settings.endpoint', 'https://panel.spotplayer.ir/license/edit/');
    });

    it('stores the skyroom key and falls back to the shipped skyroom base url', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::SKYROOM->value]), [
            'enabled' => true,
            'api_key' => 'skyroom-key',
        ])->assertOk()
            ->assertJsonPath('data.key', ProvisioningProviderSettingsEnum::SKYROOM->value)
            ->assertJsonPath('data.state', ['enabled' => true, 'configured' => true, 'ready' => true])
            ->assertJsonPath('data.settings.base_url', 'https://www.skyroom.online/skyroom/api')
            ->assertJsonPath('data.settings.api_key', SettingSecretRedactor::REDACTED);

        $stored = Setting::where('key', SettingKeyEnum::SKYROOM->value)->firstOrFail();

        expect($stored->group)->toBe('integrations')
            ->and(Crypt::decryptString($stored->value['api_key']))->toBe('skyroom-key');
    });

    it('rejects enabling skyroom without its key', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::SKYROOM->value]), [
            'enabled' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['api_key']);
    });

    it('preserves a registered skyroom secret the panel does not expose', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->skyroom()->create();

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::SKYROOM->value]), [
            'enabled' => true,
            'api_key' => 'skyroom-key',
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::SKYROOM->value)->firstOrFail();

        expect(Crypt::decryptString($stored->value['secret']))->toBe('skyroom-secret')
            ->and(Crypt::decryptString($stored->value['api_key']))->toBe('skyroom-key');
    });

    it('stores the niliroom row with its token masked', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::NILIROOM->value]), [
            'enabled'   => true,
            'base_url'  => 'https://niliroom.test',
            'api_token' => 'niliroom-token',
        ])->assertOk()
            ->assertJsonPath('data.key', ProvisioningProviderSettingsEnum::NILIROOM->value)
            ->assertJsonPath('data.state', ['enabled' => true, 'configured' => true, 'ready' => true])
            ->assertJsonPath('data.settings.base_url', 'https://niliroom.test')
            ->assertJsonPath('data.settings.api_token', SettingSecretRedactor::REDACTED);

        $stored = Setting::where('key', SettingKeyEnum::NILIROOM->value)->firstOrFail();

        expect($stored->group)->toBe('integrations')
            ->and(Crypt::decryptString($stored->value['api_token']))->toBe('niliroom-token');
    });

    it('keeps the stored niliroom token when it is omitted', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->niliroom()->create();

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::NILIROOM->value]), [
            'enabled'  => true,
            'base_url' => 'https://niliroom.example.com',
        ])->assertOk()
            ->assertJsonPath('data.state', ['enabled' => true, 'configured' => true, 'ready' => true])
            ->assertJsonPath('data.settings.api_token', SettingSecretRedactor::REDACTED);

        $stored = Setting::where('key', SettingKeyEnum::NILIROOM->value)->firstOrFail();

        expect($stored->value['api_token'])->toBe('niliroom-api-token');
    });

    it('rejects enabling niliroom without its url or token', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(provisioningProviderUrl('update', ['provider' => ProvisioningProviderSettingsEnum::NILIROOM->value]), [
            'enabled' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['base_url', 'api_token']);
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

<?php

declare(strict_types=1);

use App\Actions\Admin\Settings\Sms\BuildSmsGatewaySettingAction;
use App\Actions\Admin\Settings\Sms\UpdateSmsGatewaySettingAction;
use App\Data\Admin\Settings\Sms\SmsGatewaySettingData;
use App\Enums\PermissionEnum;
use App\Enums\Sms\SmsGatewayEnum;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Api\Admin\Settings\SmsGatewaySettingsController;
use App\Models\Setting;
use App\Services\SettingSecretRedactor;
use Illuminate\Support\Facades\Crypt;

covers(
    SmsGatewaySettingsController::class,
    SmsGatewaySettingData::class,
    BuildSmsGatewaySettingAction::class,
    UpdateSmsGatewaySettingAction::class,
    SmsGatewayEnum::class,
);

function smsGatewayUrl(string $name, array $parameters = []): string
{
    return route("api.v1.admin.settings.sms-gateways.{$name}", $parameters);
}

/*
|--------------------------------------------------------------------------
| Mutation notes
|--------------------------------------------------------------------------
|
| Survivors left after `pest --mutate` for this file, and why:
|
| - `SmsGatewaySettingData::rules()` array items (RemoveArrayItem,
|   AlwaysReturnEmptyArray): spatie/laravel-data re-derives an equivalent rule
|   from each property type (`?string` → nullable|string, `bool` → boolean), so
|   dropping an item from the array does not change the response.
| - `SmsGatewaySettingData::bodyParameters()` items: documentation-only scribe
|   metadata (marked `@codeCoverageIgnore`, like every sibling Data class); it
|   never runs while serving a request.
| - `BuildSmsGatewaySettingAction` line 40 `(string)` cast and `?? ''`: the
|   config always declares a string `label`, so both fallbacks are unreachable.
| - `UpdateSmsGatewaySettingAction` line 40 `?? null`: removing the coalesce
|   only raises an undefined-array-key warning when no row is stored, which is
|   not observable in the response.
*/

describe('index', function (): void {
    it('returns the ippanel gateway with its grouped schema and configuration defaults', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        app()->setLocale('fa');

        $response = $this->getJson(smsGatewayUrl('index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', SmsGatewayEnum::IPPANEL->value)
            ->assertJsonPath('data.0.label', 'آی‌پی‌پنل')
            ->assertJsonPath('data.0.settings.enabled', true)
            ->assertJsonPath('data.0.settings.label', 'آی‌پی‌پنل')
            ->assertJsonPath('data.0.settings.from', '1000')
            ->assertJsonPath('data.0.settings.sandbox', false)
            ->assertJsonPath('data.0.settings.api_key', SettingSecretRedactor::REDACTED);

        expect($response->json('data.0.schema'))->toBe([
            'general' => [
                ['key' => 'enabled', 'type' => 'boolean', 'label' => 'فعال', 'required' => true],
                ['key' => 'label', 'type' => 'text', 'label' => 'عنوان', 'required' => true],
                ['key' => 'from', 'type' => 'text', 'label' => 'شماره فرستنده', 'required' => true],
            ],
            'credentials' => [
                ['key' => 'api_key', 'type' => 'password', 'label' => 'کلید API', 'required' => true, 'sensitive' => true],
            ],
            'testing' => [
                ['key' => 'sandbox', 'type' => 'boolean', 'label' => 'حالت آزمایشی', 'required' => false, 'default' => false],
            ],
        ]);
    });

    it('returns stored gateway values instead of the configuration defaults', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        Setting::factory()->smsIppanelSecondary()->create();

        $response = $this->getJson(smsGatewayUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.0.settings.enabled', false)
            ->assertJsonPath('data.0.settings.label', 'IPPanel secondary')
            ->assertJsonPath('data.0.settings.from', '2000')
            ->assertJsonPath('data.0.settings.sandbox', true)
            ->assertJsonPath('data.0.settings.api_key', SettingSecretRedactor::REDACTED);
    });

    it('drops unknown stored keys from the returned settings', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        Setting::factory()->smsIppanelSecondary()->create();

        $response = $this->getJson(smsGatewayUrl('index'));

        $response->assertOk();

        expect($response->json('data.0.settings'))->not->toHaveKey('sand_box');
    });

    it('returns a stored label verbatim even when it matches a translation key', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        app()->setLocale('fa');
        Setting::factory()->smsIppanel()->create(['value' => [
            'enabled' => true,
            'label'   => 'sms.fields.enabled',
            'from'    => '1000',
            'api_key' => 'stored-key',
            'sandbox' => false,
        ]]);

        $response = $this->getJson(smsGatewayUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.0.settings.label', 'sms.fields.enabled');
    });

    it('returns 401 when unauthenticated', function (): void {
        $this->getJson(smsGatewayUrl('index'))->assertUnauthorized();
    });

    it('returns 403 without the settings view permission', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->getJson(smsGatewayUrl('index'))->assertForbidden();
    });
});

describe('show', function (): void {
    it('returns a single gateway item shaped like a list entry', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);

        $response = $this->getJson(smsGatewayUrl('show', ['gateway' => SmsGatewayEnum::IPPANEL->value]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['key', 'label', 'schema', 'settings'],
            ])
            ->assertJsonPath('data.key', SmsGatewayEnum::IPPANEL->value);
    });

    it('returns 404 for an unknown gateway key', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);

        $this->getJson(smsGatewayUrl('show', ['gateway' => 'unknown-gateway']))->assertNotFound();
    });

    it('returns 403 without the settings view permission', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->getJson(smsGatewayUrl('show', ['gateway' => SmsGatewayEnum::IPPANEL->value]))->assertForbidden();
    });

    it('returns 401 when unauthenticated', function (): void {
        $this->getJson(smsGatewayUrl('show', ['gateway' => SmsGatewayEnum::IPPANEL->value]))->assertUnauthorized();
    });
});

describe('update', function (): void {
    it('stores the submitted values and returns the masked gateway item', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $response = $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => true,
            'label'   => 'IPPanel main',
            'from'    => '2000',
            'api_key' => 'fresh-key',
            'sandbox' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.key', SmsGatewayEnum::IPPANEL->value)
            ->assertJsonPath('data.settings.enabled', true)
            ->assertJsonPath('data.settings.label', 'IPPanel main')
            ->assertJsonPath('data.settings.from', '2000')
            ->assertJsonPath('data.settings.sandbox', true)
            ->assertJsonPath('data.settings.api_key', SettingSecretRedactor::REDACTED);

        $stored = Setting::where('key', SettingKeyEnum::SMS_IPPANEL->value)->firstOrFail();

        expect($stored->value['api_key'])->not->toBe('fresh-key')
            ->and(Crypt::decryptString($stored->value['api_key']))->toBe('fresh-key')
            ->and($stored->group)->toBe('sms');
    });

    it('keeps the stored api key when the field is omitted', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->smsIppanel()->create();

        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => false,
            'label'   => 'IPPanel',
            'from'    => '1000',
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::SMS_IPPANEL->value)->firstOrFail();

        expect(Crypt::decryptString($stored->value['api_key']))->toBe('sms-ippanel-api-key');
    });

    it('keeps the stored api key when the field is null', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->smsIppanel()->create();

        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => false,
            'label'   => 'IPPanel',
            'from'    => '1000',
            'api_key' => null,
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::SMS_IPPANEL->value)->firstOrFail();

        expect(Crypt::decryptString($stored->value['api_key']))->toBe('sms-ippanel-api-key');
    });

    it('keeps the stored api key when the redacted placeholder is sent back', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->smsIppanel()->create();

        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => false,
            'label'   => 'IPPanel',
            'from'    => '1000',
            'api_key' => SettingSecretRedactor::REDACTED,
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::SMS_IPPANEL->value)->firstOrFail();

        expect(Crypt::decryptString($stored->value['api_key']))->toBe('sms-ippanel-api-key');
    });

    it('clears the stored api key on an explicit empty value', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->smsIppanel()->create();

        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => false,
            'label'   => 'IPPanel',
            'from'    => '1000',
            'api_key' => '',
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::SMS_IPPANEL->value)->firstOrFail();

        expect($stored->value['api_key'])->toBe('');
    });

    it('rejects enabling the gateway when no api key is stored', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $response = $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => true,
            'label'   => 'IPPanel',
            'from'    => '1000',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['api_key'])
            ->assertJsonPath('errors.api_key.0', __('sms.errors.enable_requires_api_key'));

        expect(Setting::where('key', SettingKeyEnum::SMS_IPPANEL->value)->exists())->toBeFalse();
    });

    it('enables the gateway with an omitted api key when a key is already stored', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->smsIppanel()->create();

        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => true,
            'label'   => 'IPPanel',
            'from'    => '1000',
        ])->assertOk()
            ->assertJsonPath('data.settings.enabled', true)
            ->assertJsonPath('data.settings.sandbox', false);
    });

    it('rejects enabling the gateway when the stored api key is cleared', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->smsIppanel()->create();

        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => true,
            'label'   => 'IPPanel',
            'from'    => '1000',
            'api_key' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['api_key']);
    });

    it('rejects a save missing the required non-secret fields', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['enabled', 'label', 'from']);
    });

    it('rejects enabling the gateway with an empty sender number', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->smsIppanel()->create();

        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => true,
            'label'   => 'IPPanel',
            'from'    => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['from']);
    });

    it('ignores unknown body keys and does not persist them', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled'  => false,
            'label'    => 'IPPanel',
            'from'     => '1000',
            'sand_box' => true,
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::SMS_IPPANEL->value)->firstOrFail();

        expect($stored->value)->toHaveKeys(['enabled', 'label', 'from', 'api_key', 'sandbox'])
            ->and($stored->value)->not->toHaveKey('sand_box');
    });

    it('returns 404 for an unknown gateway key', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsGatewayUrl('update', ['gateway' => 'unknown-gateway']), [
            'enabled' => false,
            'label'   => 'Unknown',
            'from'    => '1000',
        ])->assertNotFound();
    });

    it('returns 403 without the settings update permission', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);

        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => false,
            'label'   => 'IPPanel',
            'from'    => '1000',
        ])->assertForbidden();
    });

    it('returns 401 when unauthenticated', function (): void {
        $this->putJson(smsGatewayUrl('update', ['gateway' => SmsGatewayEnum::IPPANEL->value]), [
            'enabled' => false,
            'label'   => 'IPPanel',
            'from'    => '1000',
        ])->assertUnauthorized();
    });
});

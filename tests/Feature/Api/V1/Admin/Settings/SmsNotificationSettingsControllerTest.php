<?php

declare(strict_types=1);

use App\Actions\Admin\Settings\Sms\BuildSmsNotificationsAction;
use App\Actions\Admin\Settings\Sms\UpdateSmsNotificationsAction;
use App\Data\Admin\Settings\Sms\SmsNotificationOptionData;
use App\Data\Admin\Settings\Sms\UpdateSmsNotificationsData;
use App\Enums\PermissionEnum;
use App\Enums\Sms\SmsNotificationOptionEnum;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Api\Admin\Settings\SmsNotificationSettingsController;
use App\Models\Setting;
use App\Rules\SmsNotificationOptionKeyRule;

covers(
    SmsNotificationSettingsController::class,
    SmsNotificationOptionData::class,
    UpdateSmsNotificationsData::class,
    BuildSmsNotificationsAction::class,
    UpdateSmsNotificationsAction::class,
    SmsNotificationOptionEnum::class,
    SmsNotificationOptionKeyRule::class,
);

function smsNotificationUrl(string $name): string
{
    return route("api.v1.admin.settings.sms-notifications.{$name}");
}

describe('index', function (): void {
    it('returns every option in a fixed order with the shared schema and configuration defaults', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        app()->setLocale('fa');

        $response = $this->getJson(smsNotificationUrl('index'));

        $response->assertOk()
            ->assertJsonCount(5, 'data.options')
            ->assertJsonPath('data.options.0.key', SmsNotificationOptionEnum::OTP->value)
            ->assertJsonPath('data.options.0.label', 'کد ورود (OTP)')
            ->assertJsonPath('data.options.0.settings', ['enabled' => true, 'pattern_code' => 'mdoe1j1587'])
            ->assertJsonPath('data.options.0.state', ['enabled' => true, 'configured' => true, 'ready' => true])
            ->assertJsonPath('data.options.1.key', SmsNotificationOptionEnum::REFUND_COMPLETED->value)
            ->assertJsonPath('data.options.1.label', 'تأیید استرداد وجه')
            ->assertJsonPath('data.options.1.settings', ['enabled' => true, 'pattern_code' => ''])
            ->assertJsonPath('data.options.2.key', SmsNotificationOptionEnum::ORDER_PAID->value)
            ->assertJsonPath('data.options.2.label', 'پرداخت موفق سفارش')
            ->assertJsonPath('data.options.3.key', SmsNotificationOptionEnum::ENROLLMENT_READY->value)
            ->assertJsonPath('data.options.4.key', SmsNotificationOptionEnum::WALLET_CAMPAIGN_CREDITED->value);

        expect($response->json('data.schema'))->toBe([
            'general' => [
                ['key' => 'enabled', 'type' => 'boolean', 'label' => 'فعال', 'required' => true],
                ['key' => 'pattern_code', 'type' => 'text', 'label' => 'کد الگو', 'required' => false],
            ],
        ]);

        expect($response->json('data.options.4.label'))->toBe('واریز هدیه/کمپین کیف پول');
    });

    it('reads an option that needs no pattern as ready while it is enabled', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);

        $response = $this->getJson(smsNotificationUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.options.1.settings.pattern_code', '')
            ->assertJsonPath('data.options.1.state', ['enabled' => true, 'configured' => true, 'ready' => true]);
    });

    it('reports an enabled option without its required pattern as not ready', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        Setting::factory()->create([
            'key'   => SettingKeyEnum::SMS_NOTIFICATIONS->value,
            'value' => [
                'otp' => ['enabled' => true, 'pattern_code' => ''],
            ],
            'type'  => 'json',
            'group' => 'sms',
        ]);

        $response = $this->getJson(smsNotificationUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.options.0.settings', ['enabled' => true, 'pattern_code' => ''])
            ->assertJsonPath('data.options.0.state', ['enabled' => true, 'configured' => false, 'ready' => false])
            ->assertJsonPath('data.options.1.settings.enabled', true);
    });

    it('returns stored option values over the configuration defaults and drops unknown stored keys', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
        Setting::factory()->smsNotifications()->create();

        $response = $this->getJson(smsNotificationUrl('index'));

        $response->assertOk()
            ->assertJsonPath('data.options.0.settings', ['enabled' => false, 'pattern_code' => 'stored-otp-pattern'])
            ->assertJsonPath('data.options.2.settings', ['enabled' => true, 'pattern_code' => ''])
            ->assertJsonPath('data.options.3.settings.enabled', false);

        expect($response->json('data.options.2.settings'))->not->toHaveKey('log_type');
    });

    it('returns 401 when unauthenticated', function (): void {
        $this->getJson(smsNotificationUrl('index'))->assertUnauthorized();
    });

    it('returns 403 without the settings view permission', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->getJson(smsNotificationUrl('index'))->assertForbidden();
    });
});

describe('update', function (): void {
    it('stores the submitted options and returns the full read payload', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $response = $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp'              => ['enabled' => true, 'pattern_code' => 'new-otp-pattern'],
                'refund_completed' => ['enabled' => false, 'pattern_code' => 'refund-pattern'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(5, 'data.options')
            ->assertJsonPath('data.options.0.settings', ['enabled' => true, 'pattern_code' => 'new-otp-pattern'])
            ->assertJsonPath('data.options.1.settings', ['enabled' => false, 'pattern_code' => 'refund-pattern'])
            ->assertJsonPath('data.options.2.settings', ['enabled' => false, 'pattern_code' => ''])
            ->assertJsonPath('data.schema.general.0.key', 'enabled')
            ->assertJsonPath('data.schema.general.1.key', 'pattern_code');

        $stored = Setting::where('key', SettingKeyEnum::SMS_NOTIFICATIONS->value)->firstOrFail();

        expect($stored->value)->toBe([
            'otp'              => ['enabled' => true, 'pattern_code' => 'new-otp-pattern'],
            'refund_completed' => ['enabled' => false, 'pattern_code' => 'refund-pattern'],
        ])
            ->and($stored->group)->toBe('sms');
    });

    it('leaves an omitted option untouched', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->smsNotifications()->create();

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'order_paid' => ['enabled' => false, 'pattern_code' => 'order-pattern'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.options.0.settings', ['enabled' => false, 'pattern_code' => 'stored-otp-pattern'])
            ->assertJsonPath('data.options.2.settings', ['enabled' => false, 'pattern_code' => 'order-pattern']);

        $stored = Setting::where('key', SettingKeyEnum::SMS_NOTIFICATIONS->value)->firstOrFail();

        expect($stored->value['otp'])->toBe(['enabled' => false, 'pattern_code' => 'stored-otp-pattern'])
            ->and($stored->value['order_paid'])->toBe(['enabled' => false, 'pattern_code' => 'order-pattern']);
    });

    it('drops unknown stored sub-keys from an untouched option when saving another option', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->smsNotifications()->create();

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['enabled' => true, 'pattern_code' => 'otp-pattern'],
            ],
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::SMS_NOTIFICATIONS->value)->firstOrFail();

        expect($stored->value['order_paid'])->toBe(['enabled' => true, 'pattern_code' => '']);
    });

    it('keeps the stored pattern code when an option is disabled with an empty value', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->create([
            'key'   => SettingKeyEnum::SMS_NOTIFICATIONS->value,
            'value' => [
                'otp' => ['enabled' => true, 'pattern_code' => 'stored-otp-pattern'],
            ],
            'type'  => 'json',
            'group' => 'sms',
        ]);

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['enabled' => false, 'pattern_code' => ''],
            ],
        ])->assertOk()
            ->assertJsonPath('data.options.0.settings', ['enabled' => false, 'pattern_code' => 'stored-otp-pattern']);

        $stored = Setting::where('key', SettingKeyEnum::SMS_NOTIFICATIONS->value)->firstOrFail();

        expect($stored->value['otp'])->toBe(['enabled' => false, 'pattern_code' => 'stored-otp-pattern']);
    });

    it('keeps the configuration pattern code when a never-saved option is disabled with an empty value', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['enabled' => false, 'pattern_code' => ''],
            ],
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::SMS_NOTIFICATIONS->value)->firstOrFail();

        expect($stored->value['otp'])->toBe(['enabled' => false, 'pattern_code' => 'mdoe1j1587']);
    });

    it('saves an enabled option with an empty pattern code as not ready', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);
        Setting::factory()->smsNotifications()->create();

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['enabled' => true, 'pattern_code' => ''],
            ],
        ])->assertOk()
            ->assertJsonPath('data.options.0.settings', ['enabled' => true, 'pattern_code' => ''])
            ->assertJsonPath('data.options.0.state', ['enabled' => true, 'configured' => false, 'ready' => false]);
    });

    it('ignores unknown sub-keys inside a provided option and does not persist them', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['enabled' => true, 'pattern_code' => 'otp-pattern', 'log_type' => 'OTP', 'wired' => true],
            ],
        ])->assertOk();

        $stored = Setting::where('key', SettingKeyEnum::SMS_NOTIFICATIONS->value)->firstOrFail();

        expect($stored->value['otp'])->toBe(['enabled' => true, 'pattern_code' => 'otp-pattern']);
    });

    it('rejects a provided option missing its pattern code field', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['enabled' => true],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['options.otp.pattern_code']);

        expect(Setting::where('key', SettingKeyEnum::SMS_NOTIFICATIONS->value)->exists())->toBeFalse();
    });

    it('rejects a provided option missing its enabled field', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['pattern_code' => 'otp-pattern'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['options.otp.enabled']);
    });

    it('rejects a non boolean enabled field', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['enabled' => 'yes', 'pattern_code' => 'otp-pattern'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['options.otp.enabled']);
    });

    it('rejects a non string pattern code', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['enabled' => true, 'pattern_code' => ['nested']],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['options.otp.pattern_code']);
    });

    it('rejects an unknown option key', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp'   => ['enabled' => true, 'pattern_code' => 'otp-pattern'],
                'bogus' => ['enabled' => true, 'pattern_code' => 'bogus-pattern'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['options'])
            ->assertJsonPath('errors.options.0', __('sms.errors.unknown_notification_option', ['option' => 'bogus']));

        expect(Setting::where('key', SettingKeyEnum::SMS_NOTIFICATIONS->value)->exists())->toBeFalse();
    });

    it('rejects a missing options map', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsNotificationUrl('update'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options']);
    });

    it('rejects a non array options map', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsNotificationUrl('update'), ['options' => 'otp'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options']);
    });

    it('rejects an option that is not an object', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => 'otp-pattern',
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['options.otp']);

        expect(Setting::where('key', SettingKeyEnum::SMS_NOTIFICATIONS->value)->exists())->toBeFalse();
    });

    it('returns 403 without the settings update permission', function (): void {
        $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);

        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['enabled' => false, 'pattern_code' => ''],
            ],
        ])->assertForbidden();
    });

    it('returns 401 when unauthenticated', function (): void {
        $this->putJson(smsNotificationUrl('update'), [
            'options' => [
                'otp' => ['enabled' => false, 'pattern_code' => ''],
            ],
        ])->assertUnauthorized();
    });
});

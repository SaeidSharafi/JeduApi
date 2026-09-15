<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings\Sms;

use App\Data\Admin\Settings\Sms\SmsNotificationOptionData;
use App\Data\Admin\Settings\Sms\UpdateSmsNotificationsData;
use App\Enums\Sms\SmsNotificationOptionEnum;
use App\Enums\System\SettingKeyEnum;
use App\Services\SettingsService;

final class UpdateSmsNotificationsAction
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly BuildSmsNotificationsAction $buildNotifications,
    ) {}

    /**
     * Merge the submitted options into the stored row and return the read payload.
     *
     * The merge happens per option: an option missing from the request keeps its
     * stored value, and the whole merged map is written back, so a later partial
     * save cannot fall back to a stale configuration. The written map only ever
     * carries the declared fields, so keys an older row holds are dropped rather
     * than persisted again.
     *
     * Disabling an option never clears its pattern code — an empty `pattern_code`
     * sent with `enabled: false` keeps the previously effective code, so
     * re-enabling restores the previous behaviour. Enabling an option with an
     * empty code is allowed and simply leaves it not ready.
     *
     * @return array{schema: array<string, list<array<string, mixed>>>, options: list<array{key: string, label: string, state: array{enabled: bool, configured: bool, ready: bool}, settings: array{enabled: bool, pattern_code: string}}>}
     */
    public function handle(UpdateSmsNotificationsData $data): array
    {
        $settingKey = SettingKeyEnum::SMS_NOTIFICATIONS;
        $stored     = $this->settingsService->get($settingKey);
        $stored     = is_array($stored) ? $stored : [];

        // Rebuild the stored map from the declared fields only, so keys an older
        // row carries are dropped instead of being written back, while every
        // option the request omits keeps its stored values.
        $merged = [];

        foreach (SmsNotificationOptionEnum::cases() as $option) {
            if (array_key_exists($option->value, $stored)) {
                $merged[$option->value] = $option->resolve($stored[$option->value]);
            }
        }

        foreach ($data->options as $key => $submitted) {
            $option = SmsNotificationOptionEnum::from((string) $key);
            $values = SmsNotificationOptionData::from(is_array($submitted) ? $submitted : []);

            $patternCode = $values->pattern_code ?? '';

            if (! $values->enabled && $patternCode === '') {
                $patternCode = $option->resolve($stored[$option->value] ?? null)['pattern_code'];
            }

            $merged[$option->value] = [
                'enabled'      => $values->enabled,
                'pattern_code' => $patternCode,
            ];
        }

        $this->settingsService->set($settingKey, $merged, 'json', 'sms');

        return $this->buildNotifications->handle();
    }
}

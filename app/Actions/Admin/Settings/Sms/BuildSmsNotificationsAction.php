<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings\Sms;

use App\Data\Admin\Settings\Sms\SmsNotificationOptionData;
use App\Enums\Sms\SmsNotificationOptionEnum;
use App\Enums\System\SettingKeyEnum;
use App\Services\SettingsService;

final class BuildSmsNotificationsAction
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    /**
     * Resolve every notification option into the admin read payload.
     *
     * The shared field schema is returned once beside the options, in the order
     * the enum declares them. Each option merges its stored value over the
     * `config/sms.php` defaults, so a never-saved option still returns a
     * complete `enabled` / `pattern_code` pair.
     *
     * State is computed from the effective values rather than from any adapter:
     * `configured` means the pattern the option requires is present, `ready`
     * means the option is enabled and configured, so an enabled option without
     * its pattern is visibly not ready.
     *
     * @return array{schema: array<string, list<array<string, mixed>>>, options: list<array{key: string, label: string, state: array{enabled: bool, configured: bool, ready: bool}, settings: array{enabled: bool, pattern_code: string}}>}
     */
    public function handle(): array
    {
        $stored = $this->settingsService->get(SettingKeyEnum::SMS_NOTIFICATIONS);
        $stored = is_array($stored) ? $stored : [];

        return [
            'schema'  => SmsNotificationOptionData::schema(),
            'options' => array_map(
                fn (SmsNotificationOptionEnum $option): array => $this->buildOption($option, $stored),
                SmsNotificationOptionEnum::cases(),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{key: string, label: string, state: array{enabled: bool, configured: bool, ready: bool}, settings: array{enabled: bool, pattern_code: string}}
     */
    private function buildOption(SmsNotificationOptionEnum $option, array $stored): array
    {
        $settings   = $option->resolve($stored[$option->value] ?? null);
        $configured = ! $option->requiresPattern() || $settings['pattern_code'] !== '';

        return [
            'key'   => $option->value,
            'label' => $option->label(),
            'state' => [
                'enabled'    => $settings['enabled'],
                'configured' => $configured,
                'ready'      => $settings['enabled'] && $configured,
            ],
            'settings' => $settings,
        ];
    }
}

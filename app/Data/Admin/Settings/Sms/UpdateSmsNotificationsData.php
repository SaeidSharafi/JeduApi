<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Sms;

use App\Rules\SmsNotificationOptionKeyRule;
use Spatie\LaravelData\Data;

/**
 * The SMS notification options submitted by one save.
 *
 * `options` is a map of option key to that option's `enabled` / `pattern_code`
 * pair. An option missing from the map is left untouched, while a provided one
 * has to carry both fields.
 */
final class UpdateSmsNotificationsData extends Data
{
    /**
     * @param  array<string, mixed>  $options  Option key to its submitted settings.
     */
    public function __construct(
        public array $options,
    ) {}

    public static function rules(): array
    {
        $rules = [
            'options'   => ['required', 'array', new SmsNotificationOptionKeyRule()],
            'options.*' => ['array'],
        ];

        foreach (SmsNotificationOptionData::rules() as $field => $fieldRules) {
            $rules["options.*.{$field}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'options' => [
                'description' => 'Map of notification option key to its settings. An omitted option is left untouched; a provided option must carry both `enabled` and `pattern_code`. An unknown option key is rejected.',
                'example'     => [
                    'otp'              => ['enabled' => true, 'pattern_code' => 'mdoe1j1587'],
                    'refund_completed' => ['enabled' => true, 'pattern_code' => ''],
                ],
            ],
            'options.*.enabled' => [
                'description' => 'Whether the notification option may be sent.',
                'example'     => true,
            ],
            'options.*.pattern_code' => [
                'description' => 'Provider pattern code used for the notification option. Empty or `null` when the option needs no pattern or is not configured yet.',
                'example'     => 'mdoe1j1587',
            ],
        ];
    }
}

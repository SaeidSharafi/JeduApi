<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Sms;

use Spatie\LaravelData\Data;

/**
 * One SMS notification option as the panel edits it.
 *
 * Every option carries the same two fields, so the presentation schema and the
 * request rules live here once and are shared by the read payload and the
 * update request.
 */
final class SmsNotificationOptionData extends Data
{
    public function __construct(
        public bool $enabled,
        public ?string $pattern_code = null,
    ) {}

    /**
     * Presentation contract for the option form: group name to field list.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function schema(): array
    {
        return [
            'general' => [
                [
                    'key'      => 'enabled',
                    'type'     => 'boolean',
                    'label'    => __('sms.fields.enabled'),
                    'required' => true,
                ],
                [
                    'key'      => 'pattern_code',
                    'type'     => 'text',
                    'label'    => __('sms.fields.pattern_code'),
                    'required' => false,
                ],
            ],
        ];
    }

    /**
     * Rules for a single option, without the option key prefix.
     *
     * `pattern_code` must be present — an omitted option stays untouched, but a
     * provided one cannot be half submitted.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'enabled'      => ['required', 'boolean'],
            'pattern_code' => ['present', 'nullable', 'string'],
        ];
    }
}

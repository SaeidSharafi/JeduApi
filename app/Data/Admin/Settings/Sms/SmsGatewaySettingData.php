<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Sms;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class SmsGatewaySettingData extends Data
{
    public function __construct(
        public bool $enabled,
        public string $label,
        public string $from,
        public ?string $api_key = null,
        public bool $sandbox = false,
    ) {}

    /**
     * Presentation contract for the gateway form: group name to field list.
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
                    'key'      => 'label',
                    'type'     => 'text',
                    'label'    => __('sms.fields.label'),
                    'required' => true,
                ],
                [
                    'key'      => 'from',
                    'type'     => 'text',
                    'label'    => __('sms.fields.from'),
                    'required' => true,
                ],
            ],
            'credentials' => [
                [
                    'key'       => 'api_key',
                    'type'      => 'password',
                    'label'     => __('sms.fields.api_key'),
                    'required'  => true,
                    'sensitive' => true,
                ],
            ],
            'testing' => [
                [
                    'key'      => 'sandbox',
                    'type'     => 'boolean',
                    'label'    => __('sms.fields.sandbox'),
                    'required' => false,
                    'default'  => false,
                ],
            ],
        ];
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'label'   => ['required', 'string'],
            'from'    => ['required', 'string'],
            'api_key' => ['nullable', 'string'],
            'sandbox' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'enabled' => [
                'description' => 'Whether the gateway may be used for sending.',
                'example'     => true,
            ],
            'label' => [
                'description' => 'Display name of the gateway in the admin panel.',
                'example'     => 'آی‌پی‌پنل',
            ],
            'from' => [
                'description' => 'Sender number registered with the provider.',
                'example'     => '1000',
            ],
            'api_key' => [
                'description' => 'Provider API key. Omit or send `null` (or the masked placeholder) to keep the stored key; send an empty string to clear it.',
                'example'     => 'my-api-key',
            ],
            'sandbox' => [
                'description' => 'Whether sends are simulated instead of delivered.',
                'example'     => false,
            ],
        ];
    }
}

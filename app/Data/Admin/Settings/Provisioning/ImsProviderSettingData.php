<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Provisioning;

/**
 * The flat IMS provider settings the panel edits.
 *
 * Required fields follow the adapter's own readiness check: an enabled IMS
 * provider needs both a base URL and an API key, while a disabled one may be
 * staged with empty connection fields.
 */
final class ImsProviderSettingData extends ProvisioningProviderSettingData
{
    public function __construct(
        public bool $enabled,
        public ?string $base_url = null,
        public ?string $api_key = null,
        public ?int $timeout = null,
    ) {}

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function schema(): array
    {
        return [
            'general' => [
                [
                    'key'      => 'enabled',
                    'type'     => 'boolean',
                    'label'    => __('provisioning.fields.enabled'),
                    'required' => true,
                    'default'  => false,
                ],
                [
                    'key'      => 'timeout',
                    'type'     => 'number',
                    'label'    => __('provisioning.fields.timeout'),
                    'required' => false,
                    'default'  => 15,
                ],
            ],
            'connection' => [
                [
                    'key'      => 'base_url',
                    'type'     => 'url',
                    'label'    => __('provisioning.fields.base_url'),
                    'required' => true,
                ],
                [
                    'key'       => 'api_key',
                    'type'      => 'password',
                    'label'     => __('provisioning.fields.api_key'),
                    'required'  => true,
                    'sensitive' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'enabled'  => ['required', 'boolean'],
            'base_url' => ['nullable', 'url'],
            'api_key'  => ['nullable', 'string'],
            'timeout'  => ['nullable', 'integer', 'min:1'],
        ];
    }
}

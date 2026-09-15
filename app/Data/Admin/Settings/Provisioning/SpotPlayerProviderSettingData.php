<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Provisioning;

/**
 * The flat SpotPlayer provider settings the panel edits.
 *
 * The service URL field is `endpoint`, not `base_url`, matching the adapter and
 * the seeded row. An enabled provider needs both the endpoint and its API key;
 * `sandbox` and `timeout` keep their seeded and configured defaults.
 */
final class SpotPlayerProviderSettingData extends ProvisioningProviderSettingData
{
    public function __construct(
        public bool $enabled,
        public ?string $endpoint = null,
        public ?string $api_key = null,
        public ?bool $sandbox = null,
        public ?int $timeout = null,
    ) {}

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function schema(): array
    {
        return [
            'general' => [
                self::enabledField(),
                [
                    'key'      => 'sandbox',
                    'type'     => 'boolean',
                    'label'    => __('provisioning.fields.sandbox'),
                    'required' => false,
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
                    'key'      => 'endpoint',
                    'type'     => 'url',
                    'label'    => __('provisioning.fields.endpoint'),
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
            'enabled'  => self::enabledRule(),
            'endpoint' => ['nullable', 'url'],
            'api_key'  => ['nullable', 'string'],
            'sandbox'  => ['nullable', 'boolean'],
            'timeout'  => ['nullable', 'integer', 'min:1'],
        ];
    }
}

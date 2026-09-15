<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Provisioning;

/**
 * The flat Skyroom provider settings the panel edits.
 *
 * The base URL is optional and falls back to the shipped Skyroom API endpoint;
 * the API key alone decides whether the provider is configured, which is exactly
 * what `SkyroomService::validateConfig()` checks.
 */
final class SkyroomProviderSettingData extends ProvisioningProviderSettingData
{
    public function __construct(
        public bool $enabled,
        public ?string $base_url = null,
        public ?string $api_key = null,
    ) {}

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function schema(): array
    {
        return [
            'general' => [
                self::enabledField(),
            ],
            'connection' => [
                [
                    'key'      => 'base_url',
                    'type'     => 'url',
                    'label'    => __('provisioning.fields.base_url'),
                    'required' => false,
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
            'base_url' => ['nullable', 'url'],
            'api_key'  => ['nullable', 'string'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Provisioning;

/**
 * The flat Niliroom provider settings the panel edits.
 *
 * Niliroom is the live-session panel that replaces BBB on the admin surface
 * (ADR 0012). Its credential field is `api_token`, and readiness needs both the
 * URL and the token — resolved from configuration until an admin saves them,
 * which is also the fallback the adapter reads through
 * `provisioning.providers.niliroom`.
 */
final class NiliroomProviderSettingData extends ProvisioningProviderSettingData
{
    public function __construct(
        public bool $enabled,
        public ?string $base_url = null,
        public ?string $api_token = null,
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
                    'required' => true,
                ],
                [
                    'key'       => 'api_token',
                    'type'      => 'password',
                    'label'     => __('provisioning.fields.api_token'),
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
            'enabled'   => self::enabledRule(),
            'base_url'  => ['nullable', 'url'],
            'api_token' => ['nullable', 'string'],
        ];
    }
}

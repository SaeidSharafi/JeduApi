<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Provisioning;

/**
 * The flat Moodle provider settings the panel edits.
 */
final class MoodleProviderSettingData extends ProvisioningProviderSettingData
{
    public function __construct(
        public bool $enabled,
        public ?string $base_url = null,
        public ?string $token = null,
        public ?string $auth_userkey_token = null,
        public ?int $default_role_id = null,
        public ?string $default_login_redirect_script = null,
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
                    'key'      => 'default_role_id',
                    'type'     => 'number',
                    'label'    => __('provisioning.fields.default_role_id'),
                    'required' => false,
                    'default'  => 5,
                ],
                [
                    'key'      => 'default_login_redirect_script',
                    'type'     => 'text',
                    'label'    => __('provisioning.fields.default_login_redirect_script'),
                    'required' => false,
                    'default'  => '/my/',
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
                    'key'       => 'token',
                    'type'      => 'password',
                    'label'     => __('provisioning.fields.service_token'),
                    'required'  => true,
                    'sensitive' => true,
                ],
                [
                    'key'       => 'auth_userkey_token',
                    'type'      => 'password',
                    'label'     => __('provisioning.fields.login_token'),
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
            'enabled'                       => self::enabledRule(),
            'base_url'                      => ['nullable', 'url'],
            'token'                         => ['nullable', 'string'],
            'auth_userkey_token'            => ['nullable', 'string'],
            'default_role_id'               => ['nullable', 'integer', 'min:1'],
            'default_login_redirect_script' => ['nullable', 'string'],
            'timeout'                       => ['nullable', 'integer', 'min:1'],
        ];
    }
}

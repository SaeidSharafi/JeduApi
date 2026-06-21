<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Gateway;

use Spatie\LaravelData\Data;

final class MellatGatewaySettingData extends Data
{
    public function __construct(
        public string $terminal_id,
        public string $username,
        public ?string $password,
        public bool $test_mode = false,
    ) {}

    public static function schema(): array
    {
        return [
            'credentials' => [
                [
                    'key'      => 'terminal_id',
                    'type'     => 'text',
                    'label'    => __('payment_gateways.fields.terminal_id'),
                    'required' => true,
                ],
                [
                    'key'      => 'username',
                    'type'     => 'text',
                    'label'    => __('payment_gateways.fields.username'),
                    'required' => true,
                ],
                [
                    'key'       => 'password',
                    'type'      => 'password',
                    'label'     => __('payment_gateways.fields.password'),
                    'required'  => true,
                    'sensitive' => true,
                ],
            ],
            'testing' => [
                [
                    'key'      => 'test_mode',
                    'type'     => 'boolean',
                    'label'    => __('payment_gateways.fields.test_mode'),
                    'required' => false,
                ],
            ],
        ];
    }
}

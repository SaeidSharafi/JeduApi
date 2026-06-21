<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\Gateway;

use Spatie\LaravelData\Data;

final class DigipayGatewaySettingData extends Data
{
    public function __construct(
        public string $client_id,
        public ?string $client_secret,
        public bool $sandbox_mode = false,
    ) {}

    public static function schema(): array
    {
        return [
            'credentials' => [
                ['key' => 'client_id',     'type' => 'text',     'label' => __('payment_gateways.fields.client_id'),     'required' => true],
                ['key' => 'client_secret', 'type' => 'password', 'label' => __('payment_gateways.fields.client_secret'), 'required' => true, 'sensitive' => true],
            ],
            'testing' => [
                ['key' => 'sandbox_mode', 'type' => 'boolean', 'label' => __('payment_gateways.fields.sandbox_mode'), 'required' => false],
            ],
        ];
    }
}

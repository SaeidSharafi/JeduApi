<?php

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Services\SettingsService;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function (): void {
    $this->admin_user();
});

describe('index', function (): void {
    it('returns all payment gateways with their corresponding schemas and stored settings', function (): void {
        // Mocking SettingsService response for wallet
        $mockSettings = [
            'enabled' => true,
            'shop_enabled' => true,
            'label' => 'Wallet Payment',
            'description' => 'Pay using wallet',
            'icon' => null,
            'ims_bank_account_number' => '112233',
        ];

        $this->mock(SettingsService::class)
            ->shouldReceive('get')
            ->with(SettingKeyEnum::WALLET, [])
            ->andReturn($mockSettings)
            ->shouldReceive('get')
            ->andReturn([]); // return empty for other gateways

        $response = $this->getJson('/api/v1/admin/settings/payment-gateways');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'key',
                        'label',
                        'schema' => [
                            'general',
                        ],
                        'settings',
                    ],
                ],
            ]);

        // Assert that WALLET schema is simple, and MELLAT has config keys prefixed
        $data = $response->json('data');

        $wallet = collect($data)->firstWhere('key', PaymentMethodEnum::WALLET->value);
        expect($wallet['schema'])->not->toHaveKey('config')
            ->and($wallet['settings']['label'])->toBe('Wallet Payment');

        $mellat = collect($data)->firstWhere('key', PaymentMethodEnum::MELLAT_GATEWAY->value);

        expect($mellat['schema'])->toHaveKey('credentials');
        expect($mellat['schema'])->toHaveKey('testing');

        // Ensure config keys in Mellat's schema are prefixed with 'config.'
        $terminalIdField = collect($mellat['schema']['credentials'])->firstWhere('key', 'config.terminal_id');
        expect($terminalIdField)->not->toBeNull();
    });
});

describe('show', function (): void {
    it('returns the settings for a specific gateway', function (): void {
        $mockSettings = [
            'enabled' => true,
            'shop_enabled' => true,
            'label' => 'Mellat Bank Gateway',
            'config' => [
                'terminal_id' => '123456',
                'username' => 'user123',
            ],
        ];

        $this->mock(SettingsService::class)
            ->shouldReceive('get')
            ->with(SettingKeyEnum::MELLAT)
            ->once()
            ->andReturn($mockSettings);

        $response = $this->getJson('/api/v1/admin/settings/payment-gateways/'. PaymentMethodEnum::MELLAT_GATEWAY->value);

        $response->assertOk()
            ->assertJsonPath('data.label', 'Mellat Bank Gateway')
            ->assertJsonPath('data.config.terminal_id', '123456');
    });
});

describe('update validation', function (): void {
    it('fails when common required fields are missing', function (): void {
        $response = $this->putJson('/api/v1/admin/settings/payment-gateways/'. PaymentMethodEnum::WALLET->value, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['enabled', 'shop_enabled', 'label']);
    });

    it('fails when a complex gateway is missing its nested configuration', function (): void {
        $payload = [
            'enabled' => true,
            'shop_enabled' => true,
            'label' => 'Mellat Gateway',
            'description' => 'Pay via Mellat',
            'icon' => null,
            'ims_bank_account_number' => '9876543210',
            // 'config' is missing entirely
        ];

        $response = $this->putJson('/api/v1/admin/settings/payment-gateways/'. PaymentMethodEnum::MELLAT_GATEWAY->value, $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['config']);
    });

    it('fails when nested config fields are missing for a complex gateway', function (): void {
        $payload = [
            'enabled' => true,
            'shop_enabled' => true,
            'label' => 'Mellat Gateway',
            'description' => 'Pay via Mellat',
            'icon' => null,
            'ims_bank_account_number' => '9876543210',
            'config' => [
                'terminal_id' => '12345678',
                // 'username' and 'password' are required and missing
            ],
        ];

        $response = $this->putJson('/api/v1/admin/settings/payment-gateways/'. PaymentMethodEnum::MELLAT_GATEWAY->value, $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['config.username']);
    });

    it('fails when a simple gateway tries to submit a config payload', function (): void {
        $payload = [
            'enabled' => true,
            'shop_enabled' => true,
            'label' => 'Wallet Payment',
            'description' => 'Pay via wallet',
            'icon' => null,
            'ims_bank_account_number' => '11223344',
            'config' => [
                'some_arbitrary_key' => 'not_allowed'
            ],
        ];

        $response = $this->putJson('/api/v1/admin/settings/payment-gateways/'. PaymentMethodEnum::WALLET->value, $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['config']);
    });
});

describe('update success', function (): void {
    it('successfully updates a simple gateway config', function (): void {
        $payload = [
            'enabled' => true,
            'shop_enabled' => true,
            'label' => 'Wallet Payment',
            'description' => 'Pay via wallet',
            'icon' => 5,
            'ims_bank_account_number' => '11223344',
            'config' => null,
        ];

        $this->mock(SettingsService::class)
            ->shouldReceive('set')
            ->with(SettingKeyEnum::WALLET, $payload)
            ->once()
            ->shouldReceive('get')
            ->with(SettingKeyEnum::WALLET)
            ->once()
            ->andReturn($payload);

        $response = $this->putJson('/api/v1/admin/settings/payment-gateways/'. PaymentMethodEnum::WALLET->value, $payload);

        $response->assertOk()
            ->assertJsonPath('data.label', 'Wallet Payment')
            ->assertJsonPath('data.config', null);
    });

    it('successfully updates a complex gateway config', function (): void {
        $payload = [
            'enabled' => true,
            'shop_enabled' => true,
            'label' => 'Mellat Bank Gateway',
            'description' => 'Pay with card',
            'icon' => 12,
            'ims_bank_account_number' => '1234567890',
            'config' => [
                'terminal_id' => '999888',
                'username' => 'mellat_user',
                'password' => 'supersecret',
                'test_mode' => true,
            ],
        ];

        $this->mock(SettingsService::class)
            ->shouldReceive('set')
            ->with(SettingKeyEnum::MELLAT, $payload)
            ->once()
            ->shouldReceive('get')
            ->with(SettingKeyEnum::MELLAT)
            ->once()
            ->andReturn($payload);

        $response = $this->putJson('/api/v1/admin/settings/payment-gateways/'. PaymentMethodEnum::MELLAT_GATEWAY->value, $payload);

        $response->assertOk()
            ->assertJsonPath('data.label', 'Mellat Bank Gateway')
            ->assertJsonPath('data.config.terminal_id', '999888')
            ->assertJsonPath('data.config.test_mode', true);
    });
});

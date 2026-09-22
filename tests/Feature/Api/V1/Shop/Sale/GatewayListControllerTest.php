<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentPurposeEnum;
use App\Services\Payment\GatewayService;

use function Pest\Laravel\getJson;

it('returns list of active gateways', function (): void {
    $mockGateways = [
        [
            'key'                  => 'mellat_gateway',
            'enabled'              => true,
            'shop_enabled'         => true,
            'wallet_topup_enabled' => true,
            'label'                => 'Mellat Gateway',
            'description'          => 'Pay via Mellat',
            'icon_url'             => null,
        ],
        [
            'key'                  => 'digipay',
            'enabled'              => true,
            'shop_enabled'         => true,
            'wallet_topup_enabled' => true,
            'label'                => 'Digipay Gateway',
            'description'          => 'Pay via Digipay',
            'icon_url'             => null,
        ],
    ];

    $this->mock(GatewayService::class)
        ->shouldReceive('getShopActiveGatewaysDetails')
        ->once()
        ->with(PaymentPurposeEnum::ORDER)
        ->andReturn($mockGateways);

    $response = getJson(route('api.v1.shop.payment.gateways'));

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJson([
        'data' => $mockGateways,
    ]);
});

it('returns the wallet top-up gateway list when purpose is wallet_topup', function (): void {
    $mockGateways = [
        [
            'key'                  => 'digipay',
            'enabled'              => true,
            'shop_enabled'         => true,
            'wallet_topup_enabled' => true,
            'label'                => 'Digipay Gateway',
            'description'          => 'Pay via Digipay',
            'icon_url'             => null,
        ],
    ];

    $this->mock(GatewayService::class)
        ->shouldReceive('getShopActiveGatewaysDetails')
        ->once()
        ->with(PaymentPurposeEnum::WALLET_TOPUP)
        ->andReturn($mockGateways);

    $response = getJson(route('api.v1.shop.payment.gateways', ['purpose' => 'wallet_topup']));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJson([
        'data' => $mockGateways,
    ]);
});

it('rejects an unknown purpose with 422', function (): void {
    $this->mock(GatewayService::class)
        ->shouldNotReceive('getShopActiveGatewaysDetails');

    $response = getJson(route('api.v1.shop.payment.gateways', ['purpose' => 'checkout']));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['purpose']);
});

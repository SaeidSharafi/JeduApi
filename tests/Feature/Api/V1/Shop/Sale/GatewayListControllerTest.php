<?php

declare(strict_types=1);

use App\Services\Payment\GatewayService;

use function Pest\Laravel\getJson;

it('returns list of active gateways', function (): void {
    $mockGateways = [
        [
            'key'         => 'mellat_gateway',
            'enabled'     => true,
            'shop_enabled' => true,
            'label'       => 'Mellat Gateway',
            'description' => 'Pay via Mellat',
            'icon_url'    => null,
        ],
        [
            'key'          => 'digipay',
            'enabled'      => true,
            'shop_enabled' => true,
            'label'        => 'Digipay Gateway',
            'description'  => 'Pay via Digipay',
            'icon_url'     => null,
        ],
    ];

    $this->mock(GatewayService::class)
        ->shouldReceive('getShopActiveGatewaysDetials')
        ->once()
        ->andReturn($mockGateways);

    $response = getJson(route('api.v1.shop.payment.gateways'));

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJson([
        'data' => $mockGateways,
    ]);
});

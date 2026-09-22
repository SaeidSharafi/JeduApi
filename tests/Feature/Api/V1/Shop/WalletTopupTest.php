<?php

declare(strict_types=1);

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Api\Shop\Wallet\WalletTopupController;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Payment\PaymentProcessorFactory;
use Mockery as m;

use function Pest\Laravel\postJson;

covers(WalletTopupController::class);

uses(Tests\Support\Traits\AuthTestTrait::class);

// Unauthenticated test — no auth set up via beforeEach
it('returns 401 when unauthenticated', function (): void {
    $response = postJson(route('api.v1.shop.wallet.topup'), [
        'amount'         => 500000,
        'payment_method' => 'mellat_gateway',
    ]);

    $response->assertUnauthorized();
})->group('wallet', 'payment');

describe('authenticated wallet topup', function (): void {

    beforeEach(function (): void {
        $this->customer();
    });

    it('initiates Mellat topup and returns 201 with requires_redirect', function (): void {
        $mockProcessor = m::mock(PaymentProcessorContract::class);
        $mockProcessor->shouldReceive('process')
            ->once()
            ->andReturn(PaymentProcessResultData::pendingWithRedirect(
                payment: new Payment(),
                redirectUrl: 'https://gateway.mellat.example.com/pay',
                redirectData: ['RefId' => '123456789'],
                method: 'POST',
            ));

        $factoryMock = m::mock(PaymentProcessorFactory::class);
        $factoryMock->shouldReceive('make')
            ->with(PaymentMethodEnum::MELLAT_GATEWAY)
            ->once()
            ->andReturn($mockProcessor);

        app()->instance(PaymentProcessorFactory::class, $factoryMock);

        $response = postJson(route('api.v1.shop.wallet.topup'), [
            'amount'         => 500000,
            'payment_method' => 'mellat_gateway',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'payment',
                'requires_redirect',
                'redirect_url',
                'redirect_data',
                'redirect_method',
                'message',
            ],
        ]);
        $response->assertJson([
            'data' => [
                'requires_redirect' => true,
            ],
        ]);
    })->group('wallet', 'payment');

    it('initiates Digipay topup and returns 201 with requires_redirect', function (): void {
        $mockProcessor = m::mock(PaymentProcessorContract::class);
        $mockProcessor->shouldReceive('process')
            ->once()
            ->andReturn(PaymentProcessResultData::pendingWithRedirect(
                payment: new Payment(),
                redirectUrl: 'https://gateway.digipay.example.com/pay',
                redirectData: [],
                method: 'GET',
            ));

        $factoryMock = m::mock(PaymentProcessorFactory::class);
        $factoryMock->shouldReceive('make')
            ->with(PaymentMethodEnum::DIGIPAY)
            ->once()
            ->andReturn($mockProcessor);

        app()->instance(PaymentProcessorFactory::class, $factoryMock);

        $response = postJson(route('api.v1.shop.wallet.topup'), [
            'amount'         => 250000,
            'payment_method' => 'digipay',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'data' => [
                'requires_redirect' => true,
            ],
        ]);
    })->group('wallet', 'payment');

    it('rejects wallet as payment method with 422', function (): void {
        $response = postJson(route('api.v1.shop.wallet.topup'), [
            'amount'         => 500000,
            'payment_method' => 'wallet',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
        expect($response->json('errors.payment_method.0'))
            ->toBe('Cannot top up a wallet with the wallet payment method.');
    })->group('wallet', 'payment');

    it('rejects bank transfer as payment method with 422', function (): void {
        $response = postJson(route('api.v1.shop.wallet.topup'), [
            'amount'         => 500000,
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
    })->group('wallet', 'payment');

    it('returns 422 for invalid amount (below minimum)', function (): void {
        $response = postJson(route('api.v1.shop.wallet.topup'), [
            'amount'         => 5000,
            'payment_method' => 'mellat_gateway',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    })->group('wallet', 'payment');

    it('returns 422 for invalid payment method', function (): void {
        $response = postJson(route('api.v1.shop.wallet.topup'), [
            'amount'         => 500000,
            'payment_method' => 'bitcoin',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
    })->group('wallet', 'payment');

    it('creates payment record with order_id null and purpose wallet_topup', function (): void {
        // Track the persisted payment so the mock returns it
        $capturedPayment = null;

        $mockProcessor = m::mock(PaymentProcessorContract::class);
        $mockProcessor->shouldReceive('process')
            ->once()
            ->andReturnUsing(function (Payment $payment) use (&$capturedPayment): PaymentProcessResultData {
                $capturedPayment = $payment;

                return PaymentProcessResultData::pendingWithRedirect(
                    payment: $payment,
                    redirectUrl: 'https://gateway.mellat.example.com/pay',
                    redirectData: ['RefId' => '123456789'],
                    method: 'POST',
                );
            });

        $factoryMock = m::mock(PaymentProcessorFactory::class);
        $factoryMock->shouldReceive('make')
            ->with(PaymentMethodEnum::MELLAT_GATEWAY)
            ->once()
            ->andReturn($mockProcessor);

        app()->instance(PaymentProcessorFactory::class, $factoryMock);

        $response = postJson(route('api.v1.shop.wallet.topup'), [
            'amount'         => 100000,
            'payment_method' => 'mellat_gateway',
        ]);

        $response->assertCreated();

        // Retrieve the payment from DB via captured reference
        $payment = Payment::find($capturedPayment->id);

        expect($payment)->not->toBeNull();
        expect($payment->order_id)->toBeNull();
        expect($payment->purpose)->toBe(PaymentPurposeEnum::WALLET_TOPUP);
        expect($payment->status)->toBe(PaymentStatusEnum::PENDING);
        expect((int) $payment->amount)->toBe(100000);
    })->group('wallet', 'payment');

    it('rejects a gateway that is not enabled for wallet top-up', function (): void {
        Setting::factory()->create([
            'key'   => SettingKeyEnum::MELLAT->value,
            'value' => [
                'enabled'              => true,
                'shop_enabled'         => true,
                'wallet_topup_enabled' => false,
                'label'                => 'Mellat',
                'description'          => null,
                'icon_url'             => null,
            ],
        ]);

        $this->mock(PaymentProcessorFactory::class)->shouldNotReceive('make');

        $response = postJson(route('api.v1.shop.wallet.topup'), [
            'amount'         => 500000,
            'payment_method' => 'mellat_gateway',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
        expect(Payment::query()->count())->toBe(0);
    })->group('wallet', 'payment');

    it('allows top-up through a gateway hidden from checkout', function (): void {
        Setting::factory()->create([
            'key'   => SettingKeyEnum::MELLAT->value,
            'value' => [
                'enabled'              => true,
                'shop_enabled'         => false,
                'wallet_topup_enabled' => true,
                'label'                => 'Mellat',
                'description'          => null,
                'icon_url'             => null,
            ],
        ]);

        $mockProcessor = m::mock(PaymentProcessorContract::class);
        $mockProcessor->shouldReceive('process')
            ->once()
            ->andReturn(PaymentProcessResultData::pendingWithRedirect(
                payment: new Payment(),
                redirectUrl: 'https://gateway.mellat.example.com/pay',
                redirectData: ['RefId' => '123456789'],
                method: 'POST',
            ));

        $factoryMock = m::mock(PaymentProcessorFactory::class);
        $factoryMock->shouldReceive('make')
            ->with(PaymentMethodEnum::MELLAT_GATEWAY)
            ->once()
            ->andReturn($mockProcessor);

        app()->instance(PaymentProcessorFactory::class, $factoryMock);

        $response = postJson(route('api.v1.shop.wallet.topup'), [
            'amount'         => 500000,
            'payment_method' => 'mellat_gateway',
        ]);

        $response->assertCreated();
    })->group('wallet', 'payment');

});

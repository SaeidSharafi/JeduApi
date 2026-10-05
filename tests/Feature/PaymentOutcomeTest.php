<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\SoapClientFactory;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(App\Actions\Shop\Payment\BuildPaymentResponseAction::class);

it('uses the same outcome fields for gateway initiation on retry and topup', function (string $flow, string $gatewayOutcome, string $status, int $httpStatus): void {
    $this->customer();
    $order = $flow === 'retry' ? Order::factory()->create([
        'customer_id'            => $this->user->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 500000,
        'full_value_grand_total' => 500000,
    ]) : null;
    $soap = Mockery::mock(SoapClient::class);
    if ($gatewayOutcome === 'timeout') {
        $soap->shouldReceive('bpPayRequest')->andThrow(new SoapFault('HTTP', 'Connection timed out'));
    } else {
        $soap->shouldReceive('bpPayRequest')->andReturn((object) ['return' => $gatewayOutcome]);
    }
    $this->mock(SoapClientFactory::class)->shouldReceive('create')->andReturn($soap);

    $response = $this->postJson($flow === 'retry'
        ? route('api.v1.shop.student.orders.retry-payment', $order->increment_id)
        : route('api.v1.shop.wallet.topup'), [
            'payment_method' => 'mellat_gateway',
            'amount'         => 500000,
        ]);

    $response->assertStatus($httpStatus)
        ->assertJsonMissingPath('data.message');
    $payment = Payment::where('customer_id', $this->user->id)->sole();
    if ($status === 'pending') {
        $response->assertJsonPath('data.requires_redirect', true)
            ->assertJsonPath('data.payment.uuid', $payment->uuid)
            ->assertJsonPath('data.redirect_method', 'POST')
            ->assertJsonPath('data.redirect_data.RefId', 'outcome-ref')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.order_id')
            ->assertJsonMissingPath('data.payment_id')
            ->assertJsonMissingPath('data.error_code')
            ->assertJsonMissingPath('data.can_retry');
    } else {
        $response->assertJsonPath('data.status', $status)
            ->assertJsonPath('data.order_id', $order === null ? null : (string) $order->increment_id)
            ->assertJsonPath('data.payment_id', $payment->uuid)
            ->assertJsonPath('data.can_retry', $status === 'failed' && $flow === 'retry')
            ->assertJsonMissingPath('data.order')
            ->assertJsonMissingPath('data.payment')
            ->assertJsonMissingPath('data.requires_redirect')
            ->assertJsonMissingPath('data.redirect_url');
        expect(array_keys($response->json('data')))->toBe(['status', 'order_id', 'payment_id', 'error_code', 'can_retry']);
    }
})->with([
    'retry pending'  => ['retry', '0,outcome-ref', 'pending', 200],
    'topup pending'  => ['topup', '0,outcome-ref', 'pending', 201],
    'retry declined' => ['retry', '12', 'failed', 502],
    'topup declined' => ['topup', '12', 'failed', 502],
    'retry timeout'  => ['retry', 'timeout', 'unknown', 504],
    'topup timeout'  => ['topup', 'timeout', 'unknown', 504],
]);

it('returns failed outcome data for validation before a payment exists', function (string $flow): void {
    $this->customer();
    $order = $flow === 'retry' ? Order::factory()->create([
        'customer_id' => $this->user->id,
        'status'      => OrderStatusEnum::COMPLETED,
    ]) : null;

    $response = $this->postJson(match ($flow) {
        'checkout' => route('api.v1.shop.checkout'),
        'topup'    => route('api.v1.shop.wallet.topup'),
        'retry'    => route('api.v1.shop.student.orders.retry-payment', $order->increment_id),
    }, $flow === 'retry' ? ['payment_method' => 'wallet'] : []);

    $response->assertUnprocessable()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.order_id', $order === null ? null : (string) $order->increment_id)
        ->assertJsonPath('data.payment_id', null)
        ->assertJsonPath('data.can_retry', false)
        ->assertJsonPath('data.error_code', 'PAYMENT_VALIDATION_FAILED')
        ->assertJsonStructure(['errors']);
    expect(Payment::where('customer_id', $this->user->id)->exists())->toBeFalse();
})->with(['checkout', 'topup', 'retry']);

it('returns successful outcome after wallet payment without a gateway redirect', function (): void {
    $this->customer();
    $this->user->wallet->update(['balance' => 500000, 'gift_balance' => 0]);
    $order = Order::factory()->create([
        'customer_id'            => $this->user->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 500000,
        'full_value_grand_total' => 500000,
    ]);

    $response = $this->postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), [
        'payment_method' => 'wallet',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', __('messages.payment.completed_successfully'))
        ->assertJsonPath('data.payment.status', 'completed')
        ->assertJsonPath('data.requires_redirect', false)
        ->assertJsonPath('data.redirect_url', null)
        ->assertJsonMissingPath('data.status')
        ->assertJsonMissingPath('data.message')
        ->assertJsonMissingPath('data.error_code')
        ->assertJsonMissingPath('data.can_retry');
    expect($order->payments()->sole()->status)->toBe(PaymentStatusEnum::COMPLETED);
    expect($this->user->wallet->fresh()->balance)->toBe(0);
});

it('redirects real declined callbacks to details with failed status', function (bool $topup): void {
    $payment = $topup
        ? Payment::factory()->topup()->create(['method' => 'mellat_gateway', 'status' => PaymentStatusEnum::PENDING])
        : Payment::factory()->create(['method' => 'mellat_gateway', 'status' => PaymentStatusEnum::PENDING]);
    $transaction = App\Models\PaymentTransaction::factory()->initiated()->create(['payment_id' => $payment->id]);

    $response = $this->postJson(route('api.v1.shop.payment.gateway.callback', $payment->uuid), [
        'ResCode' => '12', 'RefId' => 'declined-ref', 'SaleOrderId' => $transaction->transaction_reference,
    ]);

    $response->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['status' => 'failed', 'payment_id' => $payment->uuid, 'error_code' => 'PAYMENT_FAILED']);
    expect(parse_url($response->headers->get('Location'), PHP_URL_PATH))->toEndWith('/'.($topup ? $payment->uuid : $payment->order->increment_id));
    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED);
})->with([false, true]);

it('redirects repeated completed callbacks with successful status', function (): void {
    $payment = Payment::factory()->create(['method' => 'mellat_gateway', 'status' => PaymentStatusEnum::COMPLETED]);

    $response = $this->postJson(route('api.v1.shop.payment.gateway.callback', $payment->uuid), ['ResCode' => '12']);

    $response->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query)->toBe(['status' => 'successful', 'payment_id' => $payment->uuid]);
    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::COMPLETED);
});

it('distinguishes Digipay declines from unconfirmed upstream failures', function (int $upstreamStatus, ?array $body, string $outcome, int $httpStatus, string $errorCode): void {
    $this->customer();
    App\Models\Setting::factory()->create([
        'key'   => App\Enums\System\SettingKeyEnum::DIGIPAY->value,
        'value' => [
            'enabled'   => true, 'wallet_topup_enabled' => true, 'sandbox_mode' => true,
            'client_id' => 'fixture-client', 'client_secret' => 'fixture-secret',
            'username'  => 'fixture-user', 'password' => 'fixture-password',
        ],
    ]);
    config()->set('payments.digipay.endpoints.sandbox.base_url', 'https://payment-outcome.digipay.test');
    config()->set('payments.digipay.paths.oauth_token', '/token');
    config()->set('payments.digipay.paths.ticket', '/ticket');
    config()->set('payments.digipay.ticket_type', 11);
    Illuminate\Support\Facades\Http::preventStrayRequests();
    Illuminate\Support\Facades\Http::fake([
        'https://payment-outcome.digipay.test/token'          => Illuminate\Support\Facades\Http::response(['access_token' => 'fixture-token', 'expires_in' => 3600]),
        'https://payment-outcome.digipay.test/ticket?type=11' => $body === null
            ? Illuminate\Support\Facades\Http::failedConnection()
            : Illuminate\Support\Facades\Http::response($body, $upstreamStatus),
    ]);

    $response = $this->postJson(route('api.v1.shop.wallet.topup'), ['amount' => 500000, 'payment_method' => 'digipay']);

    $response->assertStatus($httpStatus)
        ->assertJsonPath('data.status', $outcome)
        ->assertJsonPath('data.error_code', $errorCode)
        ->assertJsonPath('data.can_retry', false)
        ->assertJsonMissingPath('data.requires_redirect')
        ->assertJsonPath('data.order_id', null)
        ->assertJsonPath('data.payment_id', Payment::where('customer_id', $this->user->id)->sole()->uuid);
})->with([
    'known decline'        => [200, ['result' => ['status' => 1074, 'message' => 'Declined']], 'failed', 502, 'DIGIPAY_ERROR_1074'],
    'upstream unavailable' => [503, [], 'unknown', 502, 'DIGIPAY_ERROR_503'],
    'malformed reply'      => [200, [], 'unknown', 502, 'DIGIPAY_ERROR_-1'],
    'upstream timeout'     => [504, [], 'unknown', 504, 'DIGIPAY_ERROR_504'],
    'connection lost'      => [0, null, 'unknown', 504, 'UNKNOWN_ERROR'],
]);

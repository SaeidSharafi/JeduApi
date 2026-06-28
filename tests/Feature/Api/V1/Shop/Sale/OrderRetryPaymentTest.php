<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\SoapClientFactory;
use Mockery\MockInterface;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('Retry Order Payment', function (): void {
    it('allows retry payment on pending order with failed payment', function (): void {
        // Arrange: Create customer and pending order with a failed payment
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'status'                 => OrderStatusEnum::PENDING,
            'grand_total'            => 500000,
            'full_value_grand_total' => 500000, // Must match grand_total for balance_due calculation
        ]);

        Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'amount'      => 500000,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
            'status'      => PaymentStatusEnum::FAILED,
        ]);

        $user->wallet->update(['balance' => 500000]);

        // Act: Retry payment
        $response = $this->customer($user)
            ->postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), [
                'payment_method' => PaymentMethodEnum::WALLET->value,
            ]);

        // Assert: Payment retry initiated
        $response->assertOk();
        expect($response->json('data.payment'))->not->toBeNull();

        // Verify new payment record created
        expect($order->fresh()->payments()->count())->toBe(2);
    });
    it('allows retry with online gateway requiring redirect', function (): void {
        // Arrange
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'status'                 => OrderStatusEnum::PENDING,
            'grand_total'            => 500000,
            'full_value_grand_total' => 500000,
        ]);
        $expectedWsdlUrl = config('payments.mellat.test_server_url');
        $fakeRefId       = 'ABCDEF1234567890';
        $soapClientMock  = Mockery::mock(SoapClient::class);
        $this->mock(SoapClientFactory::class, function (MockInterface $mock) use ($expectedWsdlUrl, $soapClientMock) {
            $mock->shouldReceive('create')
                ->with($expectedWsdlUrl)
                ->andReturn($soapClientMock);
        });
        $soapClientMock
            ->shouldReceive('bpPayRequest')
            ->once()
            ->andReturn((object) ['return' => $fakeRefId]);
        $this->customer($user);
        // Act: Retry with gateway (requires redirect)
        $response = $this->postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), [
            'payment_method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
        ]);

        // Assert: Redirect URL provided
        $response->assertOk();

        expect($response->json('data.requires_redirect'))->toBe(true)
            ->and($response->json('data.redirect_url'))->not->toBeNull()
            ->and($response->json('data.redirect_data.RefId'))->toBe($fakeRefId)
            ->and($response->json('data.payment.status'))->toBe(PaymentStatusEnum::PENDING->value);
    });

    it('allows partial payment retry if amount specified', function (): void {
        // Arrange: Order with 500k balance
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'status'                 => OrderStatusEnum::PENDING,
            'grand_total'            => 300000,
            'full_value_grand_total' => 500000,
        ]);

        $user->wallet->update(['balance' => 500000]);

        // Act: Pay only 300k
        $response = $this->customer($user)
            ->postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), [
                'payment_method' => PaymentMethodEnum::WALLET->value,
            ]);

        // Assert: Partial payment accepted
        $response->assertOk();
        $newPayment = $order->fresh()->payments()->latest()->first();
        expect($newPayment->amount)->toBe(300000);
    });

    it('rejects retry on completed order', function (): void {
        // Arrange: Completed order
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'status'                 => OrderStatusEnum::COMPLETED,
            'grand_total'            => 500000,
            'full_value_grand_total' => 500000,
        ]);

        // Act
        $response = $this->customer($user)
            ->postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), [
                'payment_method' => PaymentMethodEnum::WALLET->value,
            ]);

        // Assert: Rejected
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['order']);
    });

    it('rejects retry on cancelled order', function (): void {
        // Arrange: Cancelled order
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'status'                 => OrderStatusEnum::CANCELLED,
            'grand_total'            => 500000,
            'full_value_grand_total' => 500000,
        ]);

        // Act
        $response = $this->customer($user)
            ->postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), [
                'payment_method' => PaymentMethodEnum::WALLET->value,
            ]);

        // Assert: Rejected
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['order']);
    });

    it('rejects retry if no balance due', function (): void {
        // Arrange: Fully paid order (create completed payment to make balance_due = 0)
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'status'                 => OrderStatusEnum::PENDING, // Still pending but fully paid
            'grand_total'            => 500000,
            'full_value_grand_total' => 500000,
        ]);

        // Create completed payment for full amount
        Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'amount'      => 500000,
            'method'      => PaymentMethodEnum::WALLET,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        // Act
        $response = $this->customer($user)
            ->postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), [
                'payment_method' => PaymentMethodEnum::WALLET->value,
            ]);

        // Assert: Rejected
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['order']);
    });

    it('rejects unauthorized user from retrying another users order', function (): void {
        // Arrange: Order belonging to different user
        $otherUser = User::factory()->create();
        $order     = Order::factory()->create([
            'customer_id'            => $otherUser->id,
            'status'                 => OrderStatusEnum::PENDING,
            'grand_total'            => 500000,
            'full_value_grand_total' => 500000,
        ]);

        // Act: Current user tries to retry other user's order
        $currentUser = User::factory()->create();
        $response    = $this->customer($currentUser)
            ->postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), [
                'payment_method' => PaymentMethodEnum::WALLET->value,
            ]);

        // Assert: Not found (order filtered by customer_id)
        $response->assertNotFound();
    });

    it('validates payment method is required', function (): void {
        // Arrange
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'status'                 => OrderStatusEnum::PENDING,
            'grand_total'            => 500000,
            'full_value_grand_total' => 500000,
        ]);

        // Act: No payment method
        $response = $this->customer($user)
            ->postJson(route('api.v1.shop.student.orders.retry-payment', $order->increment_id), []);

        // Assert
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['payment_method']);
    });

});

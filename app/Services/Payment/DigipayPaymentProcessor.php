<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use App\Services\Payment\Digipay\Data\CallbackPayload;
use App\Services\Payment\Digipay\DigipayClient;
use App\Services\Payment\Digipay\DigipayConfigRepository;
use App\Services\Payment\Digipay\DigipayException;
use App\Services\PaymentTransactionReferenceService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class DigipayPaymentProcessor implements PaymentProcessorContract
{
    public function __construct(
        private DigipayClient $client,
        private DigipayConfigRepository $config,
        private PaymentTransactionReferenceService $referenceService,
    ) {}

    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::DIGIPAY;
    }

    public function requiresRedirect(): bool
    {
        return true;
    }

    public function process(
        Order $order,
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): PaymentProcessResultData {
        $transactionReference = $this->referenceService->generate();
        $ipAddress            = request()->ip();
        $userAgent            = request()->userAgent();
        $attemptNumber        = $order->payments()
            ->where('method', PaymentMethodEnum::DIGIPAY)
            ->count() + 1;

        // Create payment record first — we need its UUID for the callback URL
        $payment = $order->payments()->create([
            'customer_id'       => $order->customer_id,
            'created_by'        => $adminUser instanceof Staff ? $adminUser->id : null,
            'amount'            => $amountToPay,
            'method'            => PaymentMethodEnum::DIGIPAY->value,
            'status'            => PaymentStatusEnum::PENDING->value,
            'admin_notes'       => $paymentData->admin_notes,
            'attempt_count'     => $attemptNumber,
            'last_attempted_at' => now(),
            'ip_address'        => $ipAddress,
            'user_agent'        => $userAgent,
        ]);

        // Embed payment UUID in the callback URL so GatewayCallbackController
        // can find the payment without extra lookup logic
        $providerId  = 'ORD-'.$order->id.'-'.$transactionReference;
        $callbackUrl = $this->config->getCallbackUrl().'?payment_uuid='.$payment->uuid;

        try {
            $ticketResponse = $this->client->createTicket(
                amount: $amountToPay,
                cellNumber: $order->customer->phone ?? '',
                providerId: $providerId,
                callbackUrl: $callbackUrl,
                description: __('messages.payment.order_description', ['order_id' => $order->increment_id]),
            );
        } catch (DigipayException $e) {
            $payment->update(['status' => PaymentStatusEnum::FAILED->value]);
            throw $e;
        }

        $payment->transactions()->create([
            'transaction_reference' => $transactionReference,
            'attempt_number'        => $attemptNumber,
            'status'                => PaymentTransactionStatusEnum::INITIATED,
            'gateway_request'       => [
                'provider_id' => $providerId,
                'amount'      => $amountToPay,
                'ticket'      => $ticketResponse->ticket,
            ],
            'initiated_at' => now(),
            'ip_address'   => $ipAddress,
            'user_agent'   => $userAgent,
        ]);

        $payment->last_gateway_reference = $providerId;
        $payment->save();

        return PaymentProcessResultData::withRedirect(
            payment: $payment,
            redirectUrl: $ticketResponse->redirectUrl,
            method: 'GET',
        );
    }

    public function verify(Payment $payment, array $callbackData): Payment
    {
        // Gatekeeper: prevent double-verification if order already has a completed payment
        if ($payment->status === PaymentStatusEnum::COMPLETED) {
            return $payment;
        }

        if ($payment->order->payments()
            ->where('id', '!=', $payment->id)
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->exists()
        ) {
            Log::warning('Duplicate payment verification blocked - order already has completed payment', [
                'payment_id' => $payment->id,
                'order_id'   => $payment->order_id,
            ]);

            throw new RuntimeException('Order already has a completed payment.');
        }

        $payload     = CallbackPayload::fromRequest($callbackData);
        $transaction = $payment->transactions()->latest()->firstOrFail();

        if (! $payload->isSuccessful()) {
            $transaction->update([
                'status'           => PaymentTransactionStatusEnum::FAILED,
                'gateway_response' => [
                    'result'   => $payload->result,
                    'psp_name' => $payload->pspName,
                ],
                'completed_at' => now(),
            ]);

            $payment->update(['status' => PaymentStatusEnum::FAILED->value]);

            return $payment->refresh();
        }

        $verifyResponse = $this->client->verify(
            trackingCode: $payload->trackingCode,
            providerId: $payload->providerId,
            type: $payload->type,
        );

        $transaction->update([
            'status'           => PaymentTransactionStatusEnum::COMPLETED,
            'gateway_response' => $verifyResponse->toTransactionData(),
            'completed_at'     => now(),
        ]);

        $payment->update([
            'status'                 => PaymentStatusEnum::COMPLETED->value,
            'last_gateway_reference' => $verifyResponse->trackingCode,
        ]);

        $payment->refresh();

        PaymentCompletedEvent::dispatch($payment);

        return $payment;
    }
}

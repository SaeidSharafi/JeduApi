<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\Gateway\DigipayException;
use App\Exceptions\Payment\DuplicatePaymentException;
use App\Exceptions\Payment\PaymentTransactionNotFoundException;
use App\Models\Payment;
use App\Services\Payment\Digipay\Data\CallbackPayload;
use App\Services\Payment\Digipay\DigipayClient;
use App\Services\Payment\Digipay\DigipayConfigRepository;
use App\Services\PaymentTransactionReferenceService;
use Illuminate\Support\Facades\Log;

final class DigipayPaymentProcessor implements PaymentProcessorContract
{
    public function __construct(
        private DigipayClient $client,
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

    public function process(Payment $payment): PaymentProcessResultData
    {
        $transaction = $this->referenceService->generateFor($payment);

        $providerId  = $transaction->transaction_reference;
        $callbackUrl = route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]);
        $cellNumber  = $payment->customer->phone ?? '';

        $description = $payment->order
            ? __('messages.payment.order_description', ['order_id' => $payment->order->increment_id])
            : __('messages.wallet.topup_description', ['amount' => $payment->amount]);

        try {
            $ticketResponse = $this->client->createTicket(
                amount: $payment->amount,
                cellNumber: $cellNumber,
                providerId: $providerId,
                callbackUrl: $callbackUrl,
                description: $description,
            );
        } catch (DigipayException $e) {
            $transaction->update([
                'status'        => PaymentTransactionStatusEnum::FAILED,
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
            $payment->update(['status' => PaymentStatusEnum::FAILED->value]);
            throw $e;
        }

        $transaction->update([
            'gateway_request' => [
                'provider_id' => $providerId,
                'amount'      => $payment->amount,
                'ticket'      => $ticketResponse->ticket,
            ],
        ]);

        $payment->last_gateway_reference = $providerId;
        $payment->save();

        return PaymentProcessResultData::pendingWithRedirect(
            payment: $payment,
            redirectUrl: $ticketResponse->redirectUrl,
            method: 'GET',
        );
    }

    public function verify(Payment $payment, array $callbackData): Payment
    {
        // Gatekeeper: prevent double-verification
        if ($payment->status === PaymentStatusEnum::COMPLETED) {
            return $payment;
        }

        // Null-order guard: only check duplicate payment when payment has an order
        if ($payment->order !== null) {
            if ($payment->order->payments()
                ->where('id', '!=', $payment->id)
                ->where('status', PaymentStatusEnum::COMPLETED)
                ->exists()
            ) {
                Log::warning('Duplicate payment verification blocked - order already has completed payment', [
                    'payment_id' => $payment->id,
                    'order_id'   => $payment->order_id,
                ]);

                throw new DuplicatePaymentException(paymentId: $payment->id, orderId: $payment->order_id);
            }
        }

        $payload     = CallbackPayload::fromRequest($callbackData);
        $transaction = $payment->transactions()
            ->where('transaction_reference', $payload->providerId)
            ->first();

        if ($transaction === null) {
            throw new PaymentTransactionNotFoundException(reference: $payload->providerId);
        }

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

        // Cross-check callback amount against payment amount
        if ($payload->amount > 0 && $payload->amount !== $payment->amount) {
            Log::error('Digipay amount mismatch', [
                'payment_id'      => $payment->id,
                'callback_amount' => $payload->amount,
                'expected_amount' => $payment->amount,
            ]);

            $transaction->update([
                'status'           => PaymentTransactionStatusEnum::FAILED,
                'gateway_response' => [
                    'result'          => $payload->result,
                    'amount_mismatch' => true,
                    'callback_amount' => $payload->amount,
                    'expected_amount' => $payment->amount,
                ],
                'completed_at' => now(),
            ]);

            $payment->update(['status' => PaymentStatusEnum::FAILED->value]);

            return $payment->refresh();
        }

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

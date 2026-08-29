<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\Payment\DuplicatePaymentException;
use App\Exceptions\Payment\PaymentTransactionNotFoundException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentTransactionReferenceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SimulatorPaymentProcessor implements PaymentProcessorContract
{
    private const SIGNATURE_HEADER = 'X-Simulator-Signature';

    public function __construct(
        private PaymentTransactionReferenceService $referenceService,
        private ?bool $available = null,
    ) {}

    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::SIMULATOR;
    }

    public function requiresRedirect(): bool
    {
        return true;
    }

    public function process(Payment $payment): PaymentProcessResultData
    {
        $this->ensureAvailable();

        if ($payment->purpose !== PaymentPurposeEnum::ORDER) {
            throw new InvalidArgumentException('The payment simulator only supports order payments.');
        }

        $existingTransaction = $payment->transactions()
            ->where('status', PaymentTransactionStatusEnum::INITIATED)
            ->latest('id')
            ->first();

        if ($existingTransaction !== null && is_string($existingTransaction->gateway_response['redirect_url'] ?? null)) {
            return PaymentProcessResultData::pendingWithRedirect(
                payment: $payment,
                redirectUrl: $existingTransaction->gateway_response['redirect_url'],
            );
        }

        $transaction = $this->referenceService->generateFor($payment);
        $callbackUrl = route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]);

        try {
            $payload   = $this->buildInitiationPayload($payment, $transaction->transaction_reference, $callbackUrl);
            $signature = $this->sign($payload);
            $response  = Http::baseUrl(mb_rtrim((string) config('payments.simulator.base_url'), '/'))
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    self::SIGNATURE_HEADER => $signature,
                ])
                ->timeout((int) config('payments.simulator.timeout', 10))
                ->post((string) config('payments.simulator.initiate_path', '/api/v1/attempts'), $payload)
                ->throw();

            $responseData = $response->json();
            $redirectUrl  = is_array($responseData) ? ($responseData['redirect_url'] ?? null) : null;

            if (! is_string($redirectUrl) || $redirectUrl === '') {
                throw new RuntimeException('The payment simulator returned no redirect URL.');
            }

            $transaction->update([
                'gateway_request'  => ['payload' => $payload, 'signature' => $signature],
                'gateway_response' => ['redirect_url' => $redirectUrl] + (is_array($responseData) ? $responseData : []),
            ]);
            $payment->update(['last_gateway_reference' => $transaction->transaction_reference]);

            return PaymentProcessResultData::pendingWithRedirect(
                payment: $payment->fresh(),
                redirectUrl: $redirectUrl,
            );
        } catch (Throwable $exception) {
            $transaction->update([
                'status'        => PaymentTransactionStatusEnum::FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at'  => now(),
            ]);
            $payment->update([
                'status'            => PaymentStatusEnum::FAILED,
                'last_attempted_at' => now(),
            ]);

            Log::error('Payment simulator initiation failed.', [
                'payment_id'      => $payment->id,
                'transaction_ref' => $transaction->transaction_reference,
                'exception'       => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $callbackData
     */
    public function verify(Payment $payment, array $callbackData): Payment
    {
        $this->ensureAvailable();

        $signature  = $callbackData['signature'] ?? request()->header(self::SIGNATURE_HEADER);
        $signedData = $callbackData;
        unset($signedData['signature']);

        if (! is_string($signature) || ! hash_equals($this->sign($signedData), $signature)) {
            throw new InvalidArgumentException('Invalid payment simulator callback signature.');
        }

        $orderReference   = $callbackData['order_reference']   ?? null;
        $paymentReference = $callbackData['payment_reference'] ?? null;
        $amount           = $callbackData['amount']            ?? null;
        $outcome          = $callbackData['outcome']           ?? null;

        if (! is_string($orderReference) || ! is_string($paymentReference) || ! is_int($amount) || ! is_string($outcome)) {
            throw new InvalidArgumentException('Invalid payment simulator callback payload.');
        }

        if ($payment->order?->increment_id !== $orderReference || $payment->amount !== $amount) {
            throw new InvalidArgumentException('Payment simulator callback references do not match the payment.');
        }

        $transaction = $payment->transactions()
            ->where('transaction_reference', $paymentReference)
            ->first();

        if ($transaction === null) {
            throw new PaymentTransactionNotFoundException(reference: $paymentReference);
        }

        if (! in_array($outcome, ['success', 'failure'], true)) {
            throw new InvalidArgumentException('Invalid payment simulator callback outcome.');
        }

        if ($transaction->status !== PaymentTransactionStatusEnum::INITIATED) {
            return $payment;
        }

        if ($outcome === 'failure') {
            $transaction->update([
                'status'           => PaymentTransactionStatusEnum::FAILED,
                'gateway_response' => $callbackData,
                'completed_at'     => now(),
            ]);
            $payment->update([
                'status'            => PaymentStatusEnum::FAILED,
                'last_attempted_at' => now(),
            ]);

            return $payment->fresh();
        }

        $order = $payment->order;

        if ($order instanceof Order && $order->payments()
            ->where('id', '!=', $payment->id)
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->exists()) {
            throw new DuplicatePaymentException(paymentId: $payment->id, orderId: $payment->order_id);
        }

        $transaction->update([
            'status'           => PaymentTransactionStatusEnum::COMPLETED,
            'gateway_response' => $callbackData,
            'completed_at'     => now(),
        ]);
        $payment->update([
            'status'                 => PaymentStatusEnum::COMPLETED,
            'last_gateway_reference' => $paymentReference,
            'last_attempted_at'      => now(),
        ]);
        PaymentCompletedEvent::dispatch($payment->fresh());

        return $payment->fresh();
    }

    /** @return array<string, mixed> */
    private function buildInitiationPayload(Payment $payment, string $paymentReference, string $callbackUrl): array
    {
        $payload = [
            'order_reference'   => $payment->order?->increment_id,
            'payment_reference' => $paymentReference,
            'amount'            => $payment->amount,
            'callback_url'      => $callbackUrl,
        ];
        $delay = $payment->data['delay_seconds'] ?? null;

        if ($delay !== null) {
            if (! is_int($delay) || $delay < 0 || $delay > (int) config('payments.simulator.max_delay_seconds', 15)) {
                throw new InvalidArgumentException('Payment simulator delay must be between 0 and 15 seconds.');
            }

            $payload['delay_seconds'] = $delay;
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function sign(array $payload): string
    {
        return hash_hmac('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (string) config('payments.simulator.secret'));
    }

    private function ensureAvailable(): void
    {
        if (! ($this->available ?? app()->environment('e2e')) || ! config('payments.simulator.enabled')) {
            throw new InvalidArgumentException('The payment simulator is available only in E2E.');
        }
    }
}

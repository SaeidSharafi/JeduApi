<?php

declare(strict_types=1);

namespace App\Actions\Shop\Payment;

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class VerifyPaymentAction
{
    public function __construct(
        private PaymentProcessorFactory $processorFactory,
    ) {}

    /**
     * Verify a pending payment after gateway callback.
     */
    /**
     * @param  array<string, mixed>  $gatewayResponse
     */
    public function handle(Payment $payment, array $gatewayResponse): Payment
    {
        return DB::transaction(function () use ($payment, $gatewayResponse): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatusEnum::COMPLETED) {
                return $payment;
            }

            if ($payment->status !== PaymentStatusEnum::PENDING) {
                if ($payment->method === PaymentMethodEnum::SIMULATOR && $payment->status === PaymentStatusEnum::FAILED) {
                    return $payment;
                }

                throw ValidationException::withMessages([
                    'payment' => __('messages.checkout.payment_not_pending', ['uuid' => $payment->uuid]),
                ]);
            }

            return $this->processorFactory->make($payment->method)->verify($payment, $gatewayResponse);
        });
    }
}

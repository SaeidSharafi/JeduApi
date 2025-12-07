<?php

declare(strict_types=1);

namespace App\Actions\Shop\Payment;

use App\Data\Shop\Payment\GatewayCallbackData;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use App\Models\PaymentTransaction;
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
    public function handle(GatewayCallbackData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $transaction = PaymentTransaction::query()
                ->where('transaction_reference', $data->transaction_refrence)
                ->firstOrFail();
            $payment = Payment::query()
                ->where('id', $transaction->payment_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Ensure payment is in PENDING state
            if ($payment->status !== PaymentStatusEnum::PENDING) {
                throw ValidationException::withMessages([
                    'payment' => "Payment {$payment->uuid} is not in pending state.",
                ]);
            }

            $processor = $this->processorFactory->make($payment->method);

            // Verify the payment
            return $processor->verify($payment, $data->gateway_response);
        });
    }
}

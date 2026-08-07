<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\Payment\InvalidPaymentPurposeException;
use App\Models\Payment;
use BadMethodCallException;

final class BankTransferPaymentProcessor implements PaymentProcessorContract
{
    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::BANK_TRANSFER;
    }

    public function requiresRedirect(): bool
    {
        return false; // Single-step payment
    }

    public function process(Payment $payment): PaymentProcessResultData
    {
        if ($payment->purpose !== PaymentPurposeEnum::ORDER) {
            throw new InvalidPaymentPurposeException(expectedPurpose: PaymentPurposeEnum::ORDER->value, actualPurpose: $payment->purpose->value);
        }

        if ($payment->status === PaymentStatusEnum::COMPLETED) {
            return PaymentProcessResultData::completed($payment);
        }

        $payment->update(['status' => PaymentStatusEnum::COMPLETED]);
        PaymentCompletedEvent::dispatch($payment->fresh());

        return PaymentProcessResultData::completed($payment->fresh());
    }

    /**
     * @param  array<string, mixed>  $callbackData
     */
    public function verify(Payment $payment, array $callbackData): Payment
    {
        // Not needed for single-step payments
        throw new BadMethodCallException(__('messages.payment.bank_transfer_no_verification'));
    }
}

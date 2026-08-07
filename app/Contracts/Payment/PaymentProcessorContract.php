<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Payment;

interface PaymentProcessorContract
{
    /**
     * Process the payment.
     * Payment is already created by PreparePendingPaymentAction.
     *
     * @return PaymentProcessResultData Contains payment record and optional redirect URL
     */
    public function process(Payment $payment): PaymentProcessResultData;

    /**
     * Verify a payment after callback from gateway.
     * Only required for multi-step processors.
     *
     * @param  Payment  $payment  The pending payment to verify
     * @param  array<string, mixed>  $callbackData  Data from gateway callback
     * @return Payment Updated payment with final status
     */
    public function verify(Payment $payment, array $callbackData): Payment;

    /**
     * Check if this processor requires customer redirect (multi-step).
     */
    public function requiresRedirect(): bool;

    /**
     * Check if this processor can handle the given payment method.
     */
    public function canHandle(PaymentMethodEnum $paymentMethod): bool;
}

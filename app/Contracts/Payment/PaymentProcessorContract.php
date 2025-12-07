<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Auth\Authenticatable;

interface PaymentProcessorContract
{
    /**
     * Process the payment for the given order.
     *
     * @return PaymentProcessResultData Contains payment record and optional redirect URL
     */
    public function process(
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay,
        ?Order $order
    ): PaymentProcessResultData;

    /**
     * Verify a payment after callback from gateway.
     * Only required for multi-step processors.
     *
     * @param  Payment  $payment  The pending payment to verify
     * @param  array  $callbackData  Data from gateway callback
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

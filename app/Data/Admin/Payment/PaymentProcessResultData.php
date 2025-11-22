<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use App\Models\Payment;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;

final class PaymentProcessResultData extends Data
{
    public function __construct(
        public readonly Payment $payment,
        public readonly ?string $redirect_url = null,
        public readonly ?array $redirect_data = null,
        #[In(['GET', 'POST'])]
        public readonly string $redirect_method = 'GET',
    ) {}

    /**
     * Create result for completed payment (single-step).
     */
    public static function completed(Payment $payment): self
    {
        return new self(
            payment: $payment,
            redirect_url: null,
            redirect_data: null,
            redirect_method: 'GET'
        );
    }

    /**
     * Create result for pending payment requiring redirect (multi-step).
     */
    public static function pendingWithRedirect(
        Payment $payment,
        string $redirectUrl,
        ?array $redirectData = null,
        string $method = 'GET'
    ): self {
        return new self(
            payment: $payment,
            redirect_url: $redirectUrl,
            redirect_data: $redirectData,
            redirect_method: $method
        );
    }

    /**
     * Check if this result requires customer redirect.
     */
    public function requiresRedirect(): bool
    {
        return ! is_null($this->redirect_url);
    }
}

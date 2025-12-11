<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use App\Data\Admin\Payment\PaymentProcessResultData;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;

final class PaymentResponseData extends Data
{
    public function __construct(
        public readonly PaymentPublicData $payment,
        public readonly bool $requires_redirect,
        public readonly ?string $redirect_url = null,
        public readonly mixed $redirect_data = null,
        #[In(['GET', 'POST'])]
        public readonly string $redirect_method = 'GET',
        public readonly ?string $message = null,
    ) {}

    public static function fromResult(PaymentProcessResultData $result, ?string $message = null): self
    {
        return new self(
            payment: PaymentPublicData::fromPayment($result->payment),
            requires_redirect: $result->requiresRedirect(),
            redirect_url: $result->redirect_url,
            redirect_data: $result->redirect_data,
            redirect_method: $result->redirect_method,
            message: $message,
        );
    }
}

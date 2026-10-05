<?php

declare(strict_types=1);

namespace App\Actions\Shop\Payment;

use App\Enums\Payment\PaymentPurposeEnum;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Throwable;

final readonly class RedirectPaymentResultAction
{
    public function __construct(private BuildPaymentResponseAction $responseAction) {}

    public function handle(Payment $payment, ?Throwable $error = null): RedirectResponse
    {
        [$subPath, $identifier] = match ($payment->purpose) {
            PaymentPurposeEnum::ORDER        => [config('payments.redirect.order'), $payment->order->increment_id],
            PaymentPurposeEnum::WALLET_TOPUP => [config('payments.redirect.topup'), $payment->uuid],
        };

        $baseUrl = mb_rtrim(config('payments.redirect.shopdomain'), '/');
        $path    = mb_trim($subPath, '/');

        $url = "{$baseUrl}/{$path}/{$identifier}";

        $outcome = $this->responseAction->handle(payment: $payment, exception: $error);
        $query   = [
            'status'     => $outcome->status,
            'payment_id' => $outcome->payment_id,
        ];
        if ($outcome->error_code !== null) {
            $query['error_code'] = $outcome->error_code;
            // Preserve the existing callback error parameters for older clients.
            $query['payment'] = $outcome->payment_id;
            $query['error']   = $outcome->error_code;
        }
        $url .= '?'.http_build_query($query);

        return redirect()->away($url);
    }
}

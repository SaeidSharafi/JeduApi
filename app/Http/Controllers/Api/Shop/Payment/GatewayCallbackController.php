<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Payment;

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Contracts\Payment\PaymentExceptionContract;
use App\Data\Shop\Payment\GatewayCallbackData;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Shop - Payment Gateway
 *
 * Handles callbacks from payment gateways after customer completes payment.
 */
final class GatewayCallbackController extends Controller
{
    /**
     * Handle callback from payment gateway.
     *
     * This endpoint receives the callback from the payment gateway after
     * the customer completes (or cancels) their payment.
     *
     * @responseFile 200 resources/responses/shop/payment/verify.json
     * @responseFile 422 resources/responses/422.json
     */
    public function handle(Request $request, Payment $payment, GatewayCallbackData $data, VerifyPaymentAction $action): RedirectResponse
    {
        $callbackPayload = $data->gateway_response ?? $request->all();

        Log::info('Gateway callback received', [
            'payment_uuid' => $payment->uuid,
            'data'         => $callbackPayload,
            'ip'           => $request->ip(),
        ]);

        try {
            $payment = $action->handle($payment, $callbackPayload);

            return $this->redirectToPaymentDetails($payment);
        } catch (PaymentExceptionContract $e) {
            Log::error('Gateway callback error', [
                'error_code'   => $e->errorCode(),
                'message'      => $e->getMessage(),
                'metadata'     => $e->metadata(),
                'payment_uuid' => $payment->uuid,
            ]);

            return $this->redirectToPaymentDetails($payment, $e->errorCode());
        } catch (Exception $e) {
            // Genuinely unrecognized failure — worth distinguishing in logs from a known gateway decline.
            Log::critical('Unhandled gateway callback error', [
                'error'        => $e->getMessage(),
                'payment_uuid' => $payment->uuid,
                'request'      => $callbackPayload,
            ]);

            return $this->redirectToPaymentDetails($payment, 'UNKNOWN_ERROR');
        }
    }

    /**
     * Redirect the customer to the order or wallet-topup details page.
     *
     * Failed callbacks target the same details page and append the gateway error code.
     */
    private function redirectToPaymentDetails(Payment $payment, ?string $error = null): RedirectResponse
    {
        [$subPath, $identifier] = match ($payment->purpose) {
            PaymentPurposeEnum::ORDER        => [config('payments.redirect.order'), $payment->order->increment_id],
            PaymentPurposeEnum::WALLET_TOPUP => [config('payments.redirect.topup'), $payment->uuid],
        };

        $baseUrl = mb_rtrim(config('payments.redirect.shopdomain'), '/');
        $path    = mb_trim($subPath, '/');

        $url = "{$baseUrl}/{$path}/{$identifier}";

        if ($error !== null) {
            $url .= '?'.http_build_query([
                'payment' => $payment->uuid,
                'error'   => $error,
            ]);
        }

        return redirect()->away($url);
    }
}

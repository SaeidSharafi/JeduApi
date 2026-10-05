<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Payment;

use App\Actions\Shop\Payment\RedirectPaymentResultAction;
use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Contracts\Payment\PaymentExceptionContract;
use App\Data\Shop\Payment\GatewayCallbackData;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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
     * Returns HTTP 302 to order/top-up details with status, payment_id, and optional error_code.
     *
     * @response 302 scenario="redirect to order or top-up details"
     */
    public function handle(Request $request, Payment $payment, GatewayCallbackData $data, VerifyPaymentAction $action, RedirectPaymentResultAction $redirectAction): RedirectResponse
    {
        $callbackPayload = $data->gateway_response ?? $request->all();

        Log::info('Gateway callback received', [
            'payment_uuid' => $payment->uuid,
            'data'         => $callbackPayload,
            'ip'           => $request->ip(),
        ]);

        try {
            $payment = $action->handle($payment, $callbackPayload);

            return $redirectAction->handle($payment);
        } catch (PaymentExceptionContract $e) {
            Log::error('Gateway callback error', [
                'error_code'   => $e->errorCode(),
                'message'      => $e->getMessage(),
                'metadata'     => $e->metadata(),
                'payment_uuid' => $payment->uuid,
            ]);

            return $redirectAction->handle($payment->fresh(), $e);
        } catch (Throwable $e) {
            // Genuinely unrecognized failure — worth distinguishing in logs from a known gateway decline.
            Log::critical('Unhandled gateway callback error', [
                'error'        => $e->getMessage(),
                'payment_uuid' => $payment->uuid,
                'request'      => $callbackPayload,
            ]);

            return $redirectAction->handle($payment->fresh(), $e);
        }
    }
}

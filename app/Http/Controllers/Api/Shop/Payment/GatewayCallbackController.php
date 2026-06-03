<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Payment;

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Data\Shop\Payment\GatewayCallbackData;
use App\Enums\Payment\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use Exception;
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
    public function __invoke(Request $request, VerifyPaymentAction $action)
    {
        Log::info('Gateway callback received', [
            'data' => $request->all(),
            'ip'   => $request->ip(),
        ]);

        // Build callback data
        $callbackData = new GatewayCallbackData(
            payment_uuid: $request->input('payment_uuid'),
            gateway_response: $request->all()
        );

        try {
            $payment = $action->handle($callbackData);

            // Redirect customer based on payment status
            if ($payment->status === PaymentStatusEnum::COMPLETED) {
                return redirect()->route('shop.payment.success', ['payment' => $payment->uuid])
                    ->with('success', 'Payment completed successfully');
            }

            return redirect()->route('shop.payment.failed', ['payment' => $payment->uuid])
                ->with('error', 'Payment failed. Please try again.');

        } catch (Exception $e) {
            Log::error('Gateway callback error', [
                'error'   => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return redirect()->route('shop.payment.error')
                ->with('error', 'An error occurred while processing your payment.');
        }
    }
}

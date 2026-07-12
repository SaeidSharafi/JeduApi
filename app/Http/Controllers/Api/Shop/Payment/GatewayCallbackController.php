<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Payment;

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Contracts\Payment\PaymentExceptionContract;
use App\Enums\Payment\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Payment;
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
     * @responseFile 200 resources/responses/shop/payment/verify.json
     * @responseFile 422 resources/responses/422.json
     */
    public function handle(Request $request, Payment $payment, VerifyPaymentAction $action)
    {
        Log::info('Gateway callback received', [
            'payment_uuid' => $payment->uuid,
            'data'         => $request->all(),
            'ip'           => $request->ip(),
        ]);

        try {
            $payment = $action->handle($payment, $request->all());

            $query = [
                'payment' => $payment->uuid,
                'purpose' => $payment->purpose->value, // 'order' | 'top_up'
            ];

            if ($payment->order) {
                $query['order'] = $payment->order->increment_id;
            }

            // Redirect customer based on payment status
            if ($payment->status === PaymentStatusEnum::COMPLETED) {
                return redirect(
                    config('payments.redirect.success').'?'.http_build_query($query)
                );
            }

            return redirect(
                config('payments.redirect.failure').'?'.http_build_query($query)
            );

        } catch (PaymentExceptionContract $e) {
            Log::error('Gateway callback error', [
                'error_code'   => $e->errorCode(),
                'message'      => $e->getMessage(),
                'metadata'     => $e->metadata(),
                'payment_uuid' => $payment->uuid,
            ]);

            return redirect(config('payments.redirect.failure').'?'.http_build_query([
                'payment' => $payment->uuid,
                'error'   => $e->errorCode(),
            ]));
        } catch (Throwable $e) {
            // Genuinely unrecognized failure — worth distinguishing in logs from a known gateway decline.
            Log::critical('Unhandled gateway callback error', [
                'error'        => $e->getMessage(),
                'payment_uuid' => $payment->uuid,
                'request'      => $request->all(),
            ]);

            return redirect(config('payments.redirect.failure').'?'.http_build_query([
                'payment' => $payment->uuid,
                'error'   => 'UNKNOWN_ERROR',
            ]));
        }
    }
}

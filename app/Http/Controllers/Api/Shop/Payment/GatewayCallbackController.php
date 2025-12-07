<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Payment;

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Data\Shop\Payment\GatewayCallbackData;
use App\Data\Shop\Payment\PaymentCompletionResponseData;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
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
     * Supports both order payments and wallet top-ups with a unified response structure.
     *
     * @responseFile 200 storage/responses/shop/payment/callback-success.json
     * @responseFile 422 storage/responses/shop/payment/callback-failed.json
     */
    public function __invoke(Request $request, VerifyPaymentAction $action)
    {
        Log::info('Gateway callback received', [
            'data' => $request->all(),
            'ip'   => $request->ip(),
        ]);

        try {
            // Build callback data
            $callbackData = new GatewayCallbackData(
                transaction_refrence: $request->input('SaleOrderId'),
                gateway_response: $request->all()
            );

            // Verify the payment
            $payment = $action->handle($callbackData);

            // Check if payment was completed successfully
            if ($payment->status !== PaymentStatusEnum::COMPLETED) {
                return response()->validationErrors([
                    'payment' => [[
                        'error_code' => 'PAYMENT_VERIFICATION_FAILED',
                        'message'    => 'Payment verification failed or was cancelled.',
                    ]],
                ]);
            }

            // Build response based on payment type
            if ($payment->payment_type === PaymentTypeEnum::ORDER) {
                // Order payment - load order with relationships
                $order = $payment->order->fresh([
                    'items.productDeliveryOption.product',
                    'customer',
                    'payments.transactions',
                ]);

                $responseData = PaymentCompletionResponseData::forOrder($payment, $order);
            } else {
                // Wallet topup - load the wallet transaction that was created
                $walletTransaction = $payment->walletTransactions()
                    ->with(['wallet', 'user'])
                    ->latest()
                    ->firstOrFail();

                $responseData = PaymentCompletionResponseData::forWalletTopup(
                    $payment,
                    $walletTransaction
                );
            }

            return response()->success($responseData, 'Payment completed successfully');
        } catch (Exception $e) {
            Log::error('Payment verification error', [
                'error'   => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your payment.',
                'errors'  => [
                    'payment' => [[
                        'error_code' => 'PAYMENT_PROCESSING_ERROR',
                        'message'    => $e->getMessage(),
                    ]],
                ],
            ], 500);
        }
    }
}

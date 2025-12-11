<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Wallet;

use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Shop\Payment\PaymentResponseData;
use App\Data\Shop\Wallet\WalletTopupRequestData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class WalletTopupController extends Controller
{
    /**
     * Initiate a wallet top-up payment.
     *
     * This endpoint allows an authenticated user to add funds to their wallet.
     *
     * **Supported Payment Methods:**
     * - `mellat_gateway`: Redirects to Mellat Bank gateway
     * - `bank_transfer`: Manual bank transfer (requires admin verification)
     *
     * **Flow:**
     * 1. Customer submits topup request
     * 2. Payment record created with status=pending
     * 3. If gateway: Customer redirected to bank
     * 4. Gateway callback → Payment verified → Wallet credited automatically
     *
     * **Response Types:**
     * - **Redirect Required:** `requires_redirect = true` - Frontend must redirect to `redirect_url`
     * - **No Redirect:** `requires_redirect = false` - Payment pending admin verification
     *
     * @responseFile storage/responses/shop/wallet/topup-result.json
     */
    public function __invoke(
        WalletTopupRequestData $data,
        PaymentProcessorFactory $processorFactory
    ) {
        $user = auth()->user();

        $paymentMethod = PaymentMethodEnum::from($data->payment_method);

        if ($paymentMethod === PaymentMethodEnum::WALLET) {
            throw ValidationException::withMessages([
                'payment_method' => ['Cannot use wallet to top up wallet. Please use a different payment method.'],
            ]);
        }

        $processor = $processorFactory->make($paymentMethod);

        $paymentData = new PaymentCreateData(
            method: $paymentMethod->value,
            data: $data->payment_data,  // For bank_transfer: transaction_id, sender_name, etc.
            admin_notes: null,
        );

        $result = $processor->process(
            paymentData: $paymentData,
            user: $user,
            amountToPay: $data->amount,
            order: null,  // ✅ No order for wallet topup
        );

        return response()->created(PaymentResponseData::fromResult(
            result: $result,
            message: $result->requiresRedirect()
                ? 'Please complete payment on the gateway page.'
                : 'Payment is pending admin verification.',
        ));
    }
}

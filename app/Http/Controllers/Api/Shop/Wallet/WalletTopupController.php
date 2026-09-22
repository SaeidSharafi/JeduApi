<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Wallet;

use App\Actions\Payment\PreparePendingPaymentAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Wallet\WalletTopupRequestData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentProcessorFactory;

/**
 * @group Shop - Wallet
 *
 * @authenticated
 */
final class WalletTopupController extends Controller
{
    /**
     * Top up wallet.
     *
     * This endpoint allows an authenticated user to add funds to their wallet.
     *
     * @responseFile resources/responses/shop/wallet/topup-result.json
     */
    public function topup(
        WalletTopupRequestData $data,
        PaymentProcessorFactory $processorFactory,
        PreparePendingPaymentAction $prepareAction,
    ): ApiResponseInterface {
        $user = auth()->user();

        // `WalletTopupRequestData` already restricted payment_method to gateways whose
        // settings mark them `wallet_topup_enabled`, so an ineligible method (wallet
        // itself, bank transfer, a disabled gateway) never reaches this point.
        $method = PaymentMethodEnum::from($data->payment_method);

        // Prepare the pending payment record (order_id = null for wallet top-up)
        $payment = $prepareAction->handle(
            actor: $user,
            customerId: $user->id,
            method: $method,
            purpose: PaymentPurposeEnum::WALLET_TOPUP,
            amount: $data->amount,
        );

        // Process payment via the appropriate gateway
        $processor = $processorFactory->make($method);
        $result    = $processor->process($payment);

        return apiResponse()->created([
            'payment'           => $result->payment,
            'requires_redirect' => $result->requiresRedirect(),
            'redirect_url'      => $result->redirect_url,
            'redirect_data'     => $result->redirect_data,
            'redirect_method'   => $result->redirect_method,
            'message'           => $result->requiresRedirect()
                ? __('messages.wallet.redirecting_to_gateway')
                : __('messages.wallet.payment_pending_verification'),
        ]);
    }
}

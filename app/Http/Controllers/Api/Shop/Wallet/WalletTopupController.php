<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Wallet;

use App\Actions\Payment\PreparePendingPaymentAction;
use App\Actions\Shop\Payment\BuildPaymentResponseAction;
use App\Actions\Shop\Payment\ProcessShopPaymentAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Wallet\WalletTopupRequestData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Http\Controllers\Controller;

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
     * @responseFile 201 resources/responses/shop/wallet/topup-result.json
     * @responseFile 422 scenario="validation rejected before payment processing" resources/responses/shop/payment/payment-validation-error.json
     * @responseFile 502 scenario="gateway initiation rejected" resources/responses/shop/payment/topup-gateway-rejected.json
     * @responseFile 504 scenario="gateway timeout; outcome unknown" resources/responses/shop/payment/topup-gateway-timeout.json
     * @responseFile 500 scenario="unexpected processing failure with saved recovery data" resources/responses/shop/payment/topup-processing-error.json
     */
    public function topup(
        WalletTopupRequestData $data,
        ProcessShopPaymentAction $processAction,
        BuildPaymentResponseAction $responseAction,
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

        $result = $processAction->handle($payment);

        return apiResponse()->created($responseAction->initiationResponse($result), $responseAction->initiationMessage($result));
    }
}

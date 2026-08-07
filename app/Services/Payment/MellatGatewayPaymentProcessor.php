<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Gateway\MellatException;
use App\Exceptions\Payment\DuplicatePaymentException;
use App\Exceptions\Payment\PaymentTransactionNotFoundException;
use App\Models\Payment;
use App\Services\PaymentTransactionReferenceService;
use App\Services\SettingsService;
use Exception;
use Illuminate\Support\Facades\Log;
use SoapFault;

/**
 * Payment processor for Mellat Gateway online payments.
 * بانک ملت
 *
 * Based on: https://github.com/meysamzandy/IranGateways
 */
final class MellatGatewayPaymentProcessor implements PaymentProcessorContract
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        public readonly SoapClientFactory $soapClientFactory,
        public readonly PaymentTransactionReferenceService $referenceService,
        private readonly SettingsService $settingsService,
    ) {
        $this->config = $this->settingsService->get(PaymentMethodEnum::MELLAT_GATEWAY->settingKey(), config('payments.mellat'));
    }

    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::MELLAT_GATEWAY;
    }

    public function requiresRedirect(): bool
    {
        return true; // Multi-step payment requiring redirect
    }

    public function process(Payment $payment): PaymentProcessResultData
    {
        $transaction          = $this->referenceService->generateFor($payment);
        $transactionReference = $transaction->transaction_reference;

        $ipAddress     = request()->ip();
        $userAgent     = request()->userAgent();
        $amount        = $payment->amount;
        $attemptNumber = $payment->attempt_count ?? 1;

        $callbackUrl = route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]);

        $gatewayRequest = [
            'terminalId'     => $this->getConfig('terminal_id'),
            'userName'       => $this->getConfig('username'),
            'userPassword'   => $this->getConfig('password'),
            'orderId'        => $transactionReference,
            'amount'         => $amount,
            'localDate'      => date('Ymd'),
            'localTime'      => date('His'),
            'additionalData' => '',
            'callBackUrl'    => $callbackUrl,
            'payerId'        => 0,
        ];

        try {
            $refId = $this->sendPayRequest($gatewayRequest);
        } catch (Exception $e) {
            $transaction->update([
                'status'        => PaymentTransactionStatusEnum::FAILED,
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
            $payment->update(['status' => PaymentStatusEnum::FAILED]);
            throw $e;
        }

        $transaction->update([
            'gateway_request'  => $gatewayRequest,
            'gateway_response' => ['RefId' => $refId],
        ]);

        $payment->last_gateway_reference = $transactionReference;
        $payment->save();

        Log::info('Mellat payment initiated', [
            'payment_id'      => $payment->id,
            'payment_uuid'    => $payment->uuid,
            'order_reference' => $payment->order->increment_id ?? 'topup',
            'transaction_ref' => $transactionReference,
            'ref_id'          => $refId,
        ]);

        return PaymentProcessResultData::pendingWithRedirect(
            payment: $payment,
            redirectUrl: $this->getGateWayUrl(),
            redirectData: [
                'RefId' => $refId,
            ],
            method: 'POST'
        );
    }

    /**
     * @param  array<string, mixed>  $callbackData
     */
    public function verify(Payment $payment, array $callbackData): Payment
    {
        // Gatekeeper: prevent double-verification if already completed
        if ($payment->status === PaymentStatusEnum::COMPLETED) {
            return $payment;
        }

        // Null-order guard for duplicate payment check
        if ($payment->order !== null) {
            if ($payment->order->payments()
                ->where('status', PaymentStatusEnum::COMPLETED)
                ->where('id', '!=', $payment->id)
                ->exists()
            ) {
                Log::warning('Duplicate payment verification blocked - order already has completed payment', [
                    'payment_id' => $payment->id,
                    'order_id'   => $payment->order_id,
                ]);
                throw new DuplicatePaymentException(paymentId: $payment->id, orderId: $payment->order_id);
            }
        }

        Log::info('Verifying Mellat payment', [
            'payment_id'    => $payment->id,
            'callback_data' => $callbackData,
        ]);
        $transaction = null;
        try {
            // Extract data from callback
            $refId           = $callbackData['RefId']           ?? null;
            $resCode         = $callbackData['ResCode']         ?? null;
            $saleOrderId     = $callbackData['SaleOrderId']     ?? null;
            $saleReferenceId = $callbackData['SaleReferenceId'] ?? null;

            if ($refId === null || $refId === '' || $resCode === null || $resCode === '') {
                throw new MellatException(__('payment_gateways.mellat.errors.invalid_callback'));
            }

            $transaction = $payment->transactions()
                ->where('transaction_reference', $saleOrderId)
                ->first();

            if ($transaction === null) {
                throw new PaymentTransactionNotFoundException(reference: $saleOrderId);
            }

            // Check if transaction was successful
            if ($resCode !== '0') {
                // Payment failed
                $errorMessage = $this->getMellatErrorMessage($resCode);

                $transaction->update([
                    'status'           => PaymentTransactionStatusEnum::FAILED,
                    'gateway_response' => $callbackData,
                    'completed_at'     => now(),
                    'error_code'       => $resCode,
                    'error_message'    => $errorMessage,
                ]);

                $payment->update([
                    'status'            => PaymentStatusEnum::FAILED,
                    'last_attempted_at' => now(),
                ]);

                Log::warning('Mellat payment failed', [
                    'payment_id'      => $payment->id,
                    'transaction_ref' => $transaction->transaction_reference,
                    'res_code'        => $resCode,
                ]);

                return $payment;
            }

            // FinalAmount cross-check
            $finalAmount = (int) ($callbackData['FinalAmount'] ?? 0);
            if ($finalAmount !== $payment->amount) {
                $transaction->update([
                    'status'           => PaymentTransactionStatusEnum::FAILED,
                    'gateway_response' => $callbackData,
                    'completed_at'     => now(),
                    'error_message'    => __('payment_gateways.mellat.errors.amount_mismatch', ['expected' => $payment->amount, 'actual' => $finalAmount]),
                ]);

                $payment->update([
                    'status'            => PaymentStatusEnum::FAILED,
                    'last_attempted_at' => now(),
                ]);

                Log::warning('Mellat amount mismatch', [
                    'payment_id' => $payment->id,
                    'expected'   => $payment->amount,
                    'got'        => $finalAmount,
                ]);

                return $payment;
            }

            // Call bpVerifyRequest to verify with Mellat servers
            $verifyResult = $this->verifyWithMellat($refId, $saleOrderId, $saleReferenceId);

            if ($verifyResult !== true) {
                // Verification failed
                $transaction->update([
                    'status'           => PaymentTransactionStatusEnum::FAILED,
                    'gateway_response' => array_merge($callbackData, ['verification_failed' => true]),
                    'completed_at'     => now(),
                    'error_message'    => __('payment_gateways.mellat.errors.verification_failed_short'),
                ]);

                $payment->update([
                    'status'            => PaymentStatusEnum::FAILED,
                    'last_attempted_at' => now(),
                ]);

                Log::error('Mellat verification failed', [
                    'payment_id'      => $payment->id,
                    'transaction_ref' => $transaction->transaction_reference,
                    'ref_id'          => $refId,
                ]);

                return $payment;
            }

            // Call bpSettleRequest to settle the transaction
            $settleResult = $this->settleWithMellat($refId, $saleOrderId, $saleReferenceId);

            if ($settleResult !== true) {
                // Settlement failed - this is critical, payment was verified but not settled
                $transaction->update([
                    'status'           => PaymentTransactionStatusEnum::FAILED,
                    'gateway_response' => array_merge($callbackData, [
                        'verification_success' => true,
                        'settlement_failed'    => true,
                    ]),
                    'completed_at'  => now(),
                    'error_message' => __('payment_gateways.mellat.errors.settlement_after_verification_failed'),
                ]);

                $payment->update([
                    'status'            => PaymentStatusEnum::FAILED,
                    'last_attempted_at' => now(),
                ]);

                Log::critical('Mellat settlement failed after successful verification', [
                    'payment_id'      => $payment->id,
                    'transaction_ref' => $transaction->transaction_reference,
                    'ref_id'          => $refId,
                ]);

                return $payment;
            }

            // SUCCESS! Payment verified and settled
            $transaction->update([
                'status'           => PaymentTransactionStatusEnum::COMPLETED,
                'gateway_response' => array_merge($callbackData, [
                    'verified_at' => now()->toISOString(),
                    'settled_at'  => now()->toISOString(),
                ]),
                'completed_at' => now(),
            ]);

            $payment->update([
                'status'                 => PaymentStatusEnum::COMPLETED,
                'last_gateway_reference' => $saleReferenceId,
                'last_attempted_at'      => now(),
            ]);

            // Dispatch completion event ONLY after successful verification
            PaymentCompletedEvent::dispatch($payment);

            Log::info('Mellat payment completed successfully', [
                'payment_id'      => $payment->id,
                'transaction_ref' => $transaction->transaction_reference,
                'ref_id'          => $refId,
                'sale_ref_id'     => $saleReferenceId,
            ]);

            return $payment;

        } catch (Exception $e) {
            Log::error('Error verifying Mellat payment', [
                'payment_id'      => $payment->id,
                'transaction_ref' => $transaction->transaction_reference ?? null,
                'error'           => $e->getMessage(),
            ]);

            $payment->transactions()
                ->where('status', PaymentTransactionStatusEnum::INITIATED)
                ->when($transaction, fn ($q) => $q->where('id', $transaction->id))
                ->update(
                    [
                        'status'           => PaymentTransactionStatusEnum::FAILED,
                        'gateway_response' => $callbackData,
                        'completed_at'     => now(),
                        'error_message'    => $e->getMessage(),
                    ]
                );

            $payment->update([
                'status'            => PaymentStatusEnum::FAILED,
                'last_attempted_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * get RefId from Mellat Gateway
     */
    /**
     * @param  array<string, mixed>  $params
     */
    private function sendPayRequest(array $params): string
    {
        try {
            $soap     = $this->soapClientFactory->create($this->getWsdlUrl());
            $response = $soap->bpPayRequest($params);
        } catch (SoapFault $e) {
            throw new CustomValidationException(__('validation.custom.checkout.payment.gateway_connection_error'));
        }

        // Extract result from response
        $result = $response->return ?? null;

        if (! $result) {
            throw new MellatException(__('payment_gateways.mellat.errors.invalid_response'));
        }

        // Check if request was successful (result should be RefId if successful)
        if (mb_strlen($result) < 10) {
            // Error code returned instead of RefId
            throw new MellatException($result);
        }

        // Return RefId
        return $result;
    }

    private function verifyWithMellat(string $refId, string $saleOrderId, string $saleReferenceId): bool
    {
        try {
            $soap     = $this->soapClientFactory->create($this->getWsdlUrl());
            $response = $soap->bpVerifyRequest([
                'terminalId'      => $this->getConfig('terminal_id'),
                'userName'        => $this->getConfig('username'),
                'userPassword'    => $this->getConfig('password'),
                'orderId'         => $saleOrderId,
                'orderIdLong'     => $saleOrderId,
                'saleOrderId'     => $saleOrderId,
                'saleReferenceId' => $saleReferenceId,
            ]);

            return $response->return === '0';
        } catch (SoapFault $e) {
            Log::error('Mellat verification SOAP error', ['error' => $e->getMessage()]);
            throw new MellatException(__('payment_gateways.mellat.errors.verification_failed', ['message' => $e->getMessage()]));
        }
    }

    private function settleWithMellat(string $refId, string $saleOrderId, string $saleReferenceId): bool
    {
        try {
            $soap     = $this->soapClientFactory->create($this->getWsdlUrl());
            $response = $soap->bpSettleRequest([
                'terminalId'      => $this->getConfig('terminal_id'),
                'userName'        => $this->getConfig('username'),
                'userPassword'    => $this->getConfig('password'),
                'orderId'         => $saleOrderId,
                'orderIdLong'     => $saleOrderId,
                'saleOrderId'     => $saleOrderId,
                'saleReferenceId' => $saleReferenceId,
            ]);

            // 0 = successful, 45 = already settled
            return $response->return === '0' || $response->return === '45';
        } catch (SoapFault $e) {
            Log::error('Mellat settlement SOAP error', ['error' => $e->getMessage()]);
            throw new MellatException(__('payment_gateways.mellat.errors.settlement_failed', ['message' => $e->getMessage()]));
        }
    }

    private function getMellatErrorMessage(string $code): string
    {
        // Map Mellat error codes to messages
        $errors = [
            '11'  => __('payment_gateways.mellat.error_codes.11'),
            '12'  => __('payment_gateways.mellat.error_codes.12'),
            '13'  => __('payment_gateways.mellat.error_codes.13'),
            '14'  => __('payment_gateways.mellat.error_codes.14'),
            '15'  => __('payment_gateways.mellat.error_codes.15'),
            '16'  => __('payment_gateways.mellat.error_codes.16'),
            '17'  => __('payment_gateways.mellat.error_codes.17'),
            '18'  => __('payment_gateways.mellat.error_codes.18'),
            '19'  => __('payment_gateways.mellat.error_codes.19'),
            '111' => __('payment_gateways.mellat.error_codes.111'),
            '112' => __('payment_gateways.mellat.error_codes.112'),
            '113' => __('payment_gateways.mellat.error_codes.113'),
            '114' => __('payment_gateways.mellat.error_codes.114'),
            '21'  => __('payment_gateways.mellat.error_codes.21'),
            '23'  => __('payment_gateways.mellat.error_codes.23'),
            '24'  => __('payment_gateways.mellat.error_codes.24'),
            '25'  => __('payment_gateways.mellat.error_codes.25'),
            '31'  => __('payment_gateways.mellat.error_codes.31'),
            '32'  => __('payment_gateways.mellat.error_codes.32'),
            '33'  => __('payment_gateways.mellat.error_codes.33'),
            '34'  => __('payment_gateways.mellat.error_codes.34'),
            '35'  => __('payment_gateways.mellat.error_codes.35'),
            '41'  => __('payment_gateways.mellat.error_codes.41'),
            '42'  => __('payment_gateways.mellat.error_codes.42'),
            '43'  => __('payment_gateways.mellat.error_codes.43'),
            '44'  => __('payment_gateways.mellat.error_codes.44'),
            '45'  => __('payment_gateways.mellat.error_codes.45'),
            '46'  => __('payment_gateways.mellat.error_codes.46'),
            '47'  => __('payment_gateways.mellat.error_codes.47'),
            '48'  => __('payment_gateways.mellat.error_codes.48'),
            '49'  => __('payment_gateways.mellat.error_codes.49'),
            '412' => __('payment_gateways.mellat.error_codes.412'),
            '413' => __('payment_gateways.mellat.error_codes.413'),
            '414' => __('payment_gateways.mellat.error_codes.414'),
            '415' => __('payment_gateways.mellat.error_codes.415'),
            '416' => __('payment_gateways.mellat.error_codes.416'),
            '417' => __('payment_gateways.mellat.error_codes.417'),
            '418' => __('payment_gateways.mellat.error_codes.418'),
            '419' => __('payment_gateways.mellat.error_codes.419'),
            '421' => __('payment_gateways.mellat.error_codes.421'),
            '51'  => __('payment_gateways.mellat.error_codes.51'),
            '54'  => __('payment_gateways.mellat.error_codes.54'),
            '55'  => __('payment_gateways.mellat.error_codes.55'),
            '56'  => __('payment_gateways.mellat.error_codes.56'),
            '57'  => __('payment_gateways.mellat.error_codes.57'),
            '58'  => __('payment_gateways.mellat.error_codes.58'),
            '61'  => __('payment_gateways.mellat.error_codes.61'),
        ];

        return $errors[$code] ?? __('messages.integration.unknown_error', ['code' => $code]);
    }

    private function getWsdlUrl(): string
    {
        $isTest = $this->getConfig('test_mode', true);

        return $isTest ? config('payments.mellat.test_server_url') : config('payments.mellat.server_url');
    }

    private function getGateWayUrl(): string
    {
        $isTest = $this->getConfig('test_mode', true);

        return $isTest ? config('payments.mellat.test_gateway_url') : config('payments.mellat.gateway_url');
    }

    /**
     * @return mixed
     */
    private function getConfig(string $key, mixed $default = null)
    {
        return data_get($this->config, $key, $default);
    }
}

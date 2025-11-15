<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Gateway\MellatException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

/**
 * Payment processor for Mellat Gateway online payments.
 * بانک ملت
 *
 * Based on: https://github.com/meysamzandy/IranGateways
 */
final class MellatGatewayPaymentProcessor implements PaymentProcessorContract
{

    public function __construct(public readonly SoapClientFactory $soapClientFactory)
    {}

    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::MELLAT_GATEWAY;
    }

    public function requiresRedirect(): bool
    {
        return true; // Multi-step payment requiring redirect
    }

    public function process(
        Order $order,
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): PaymentProcessResultData {
        // Step 1: Send payment request to Mellat Gateway
        $refId = $this->sendPayRequest($amountToPay, $order, $paymentData);

        // Step 2: Create PENDING payment record
        $payment = $order->payments()->create([
            'customer_id' => $order->customer_id,
            'created_by'  => $adminUser instanceof Staff ? $adminUser->id : null,
            'amount'      => $amountToPay,
            'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
            'status'      => PaymentStatusEnum::PENDING, // CRITICAL: Must be PENDING
            'admin_notes' => $paymentData->admin_notes,
            'data'        => [
                'gateway'        => 'mellat',
                'transaction_id' => $refId,
                'initiated_at'   => now()->toISOString(),
            ],
        ]);

        Log::info('Mellat payment initiated', [
            'payment_id'   => $payment->id,
            'payment_uuid' => $payment->uuid,
            'order_id'     => $order->increment_id,
            'ref_id'       => $refId,
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

    public function verify(Payment $payment, array $callbackData): Payment
    {
        // Step 4: Verify the callback from Mellat
        Log::info('Verifying Mellat payment', [
            'payment_id'    => $payment->id,
            'callback_data' => $callbackData,
        ]);

        try {
            // Extract data from callback
            $refId           = $callbackData['RefId']           ?? null;
            $resCode         = $callbackData['ResCode']         ?? null;
            $saleOrderId     = $callbackData['SaleOrderId']     ?? null;
            $saleReferenceId = $callbackData['SaleReferenceId'] ?? null;

            // Validate callback data
            if (! $refId || ! $resCode) {
                throw new MellatException('Invalid callback data from Mellat');
            }

            // Check if transaction was successful
            if ($resCode !== '0') {
                // Payment failed
                $payment->update([
                    'status' => PaymentStatusEnum::FAILED,
                    'data'   => array_merge($payment->data ?? [], [
                        'callback_received_at' => now()->toISOString(),
                        'callback_data'        => $callbackData,
                        'failure_reason'       => $this->getMellatErrorMessage($resCode),
                    ]),
                ]);

                Log::warning('Mellat payment failed', [
                    'payment_id' => $payment->id,
                    'res_code'   => $resCode,
                ]);

                return $payment;
            }

            // Call bpVerifyRequest to verify with Mellat servers
            $verifyResult = $this->verifyWithMellat($refId, $saleOrderId, $saleReferenceId);

            if ($verifyResult !== true) {
                // Verification failed
                $payment->update([
                    'status' => PaymentStatusEnum::FAILED,
                    'data'   => array_merge($payment->data ?? [], [
                        'callback_received_at' => now()->toISOString(),
                        'verification_failed'  => true,
                        'callback_data'        => $callbackData,
                    ]),
                ]);

                Log::error('Mellat verification failed', [
                    'payment_id' => $payment->id,
                    'ref_id'     => $refId,
                ]);

                return $payment;
            }

            // Call bpSettleRequest to settle the transaction
            $settleResult = $this->settleWithMellat($refId, $saleOrderId, $saleReferenceId);

            if ($settleResult !== true) {
                // Settlement failed - this is critical, payment was verified but not settled
                $payment->update([
                    'status' => PaymentStatusEnum::FAILED,
                    'data'   => array_merge($payment->data ?? [], [
                        'callback_received_at' => now()->toISOString(),
                        'verification_success' => true,
                        'settlement_failed'    => true,
                        'callback_data'        => $callbackData,
                    ]),
                ]);

                Log::critical('Mellat settlement failed after successful verification', [
                    'payment_id' => $payment->id,
                    'ref_id'     => $refId,
                ]);

                return $payment;
            }

            // SUCCESS! Payment verified and settled
            $payment->update([
                'status' => PaymentStatusEnum::COMPLETED,
                'data'   => array_merge($payment->data ?? [], [
                    'callback_received_at' => now()->toISOString(),
                    'verified_at'          => now()->toISOString(),
                    'settled_at'           => now()->toISOString(),
                    'callback_data'        => $callbackData,
                ]),
            ]);

            // Dispatch completion event ONLY after successful verification
            PaymentCompletedEvent::dispatch($payment);

            Log::info('Mellat payment completed successfully', [
                'payment_id' => $payment->id,
                'ref_id'     => $refId,
            ]);

            return $payment;

        } catch (Exception $e) {
            Log::error('Error verifying Mellat payment', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);

            $payment->update([
                'status' => PaymentStatusEnum::FAILED,
                'data'   => array_merge($payment->data ?? [], [
                    'verification_error' => $e->getMessage(),
                    'callback_data'      => $callbackData,
                ]),
            ]);

            throw $e;
        }
    }

    private function getConfig(): array
    {
        return [
            'terminalId'  => config('payments.mellat.terminal_id'),
            'username'    => config('payments.mellat.username'),
            'password'    => config('payments.mellat.password'),
            'callbackUrl' => config('payments.mellat.callback_url'),
        ];
    }

    /**
     * get RefId from Mellat Gateway
     */
    private function sendPayRequest(int $amountToPay, Order $order, PaymentCreateData $paymentData): string
    {
        $config = $this->getConfig();

        $params = [
            'terminalId'     => $config['terminalId'],
            'userName'       => $config['username'],
            'userPassword'   => $config['password'],
            'orderId'        => $order->increment_id,
            'amount'         => $amountToPay,
            'localDate'      => date('Ymd'),
            'localTime'      => date('His'),
            'additionalData' => '',
            'callBackUrl'    => $config['callbackUrl'],
            'payerId'        => 0,
        ];

        try {
            $soap = $this->soapClientFactory->create($this->getWsdlUrl());
            $response = $soap->bpPayRequest($params);
        } catch (SoapFault $e) {
            throw new CustomValidationException(__('validation.custom.checkout.payment.gateway_connection_error'));
        }

        // Extract result from response
        $result = $response->return ?? null;

        if (! $result) {
            throw new MellatException('Invalid response from Mellat gateway');
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
        $config = $this->getConfig();

        try {
            $soap = $this->soapClientFactory->create($this->getWsdlUrl());
            $response = $soap->bpVerifyRequest([
                'terminalId'      => $config['terminalId'],
                'userName'        => $config['username'],
                'userPassword'    => $config['password'],
                'orderId'         => $saleOrderId,
                'orderIdLong'     => $saleOrderId,
                'saleOrderId'     => $saleOrderId,
                'saleReferenceId' => $saleReferenceId,
            ]);

            return $response->return === '0';
        } catch (SoapFault $e) {
            Log::error('Mellat verification SOAP error', ['error' => $e->getMessage()]);
            throw new MellatException('Gateway verification failed: '.$e->getMessage());
        }
    }

    private function settleWithMellat(string $refId, string $saleOrderId, string $saleReferenceId): bool
    {
        $config = $this->getConfig();

        try {
            $soap = $this->soapClientFactory->create($this->getWsdlUrl());
            $response = $soap->bpSettleRequest([
                'terminalId'      => $config['terminalId'],
                'userName'        => $config['username'],
                'userPassword'    => $config['password'],
                'orderId'         => $saleOrderId,
                'orderIdLong'     => $saleOrderId,
                'saleOrderId'     => $saleOrderId,
                'saleReferenceId' => $saleReferenceId,
            ]);

            // 0 = successful, 45 = already settled
            return $response->return === '0' || $response->return === '45';
        } catch (SoapFault $e) {
            Log::error('Mellat settlement SOAP error', ['error' => $e->getMessage()]);
            throw new MellatException('Gateway settlement failed: '.$e->getMessage());
        }
    }

    private function getMellatErrorMessage(string $code): string
    {
        // Map Mellat error codes to messages
        $errors = [
            '11'  => 'Invalid card number',
            '12'  => 'No sufficient funds',
            '13'  => 'Incorrect PIN',
            '14'  => 'Allowable number of PIN tries exceeded',
            '15'  => 'Invalid card',
            '16'  => 'Allowable number of transactions exceeded',
            '17'  => 'User canceled transaction',
            '18'  => 'Expired card',
            '19'  => 'Allowable number of amount exceeded',
            '111' => 'Invalid issuer',
            '112' => 'Card holder has restrictions',
            '113' => 'Issuer has problems',
            '114' => 'Invalid merchant',
            '21'  => 'Invalid transaction',
            '23'  => 'Invalid currency',
            '24'  => 'Invalid amount',
            '25'  => 'Invalid CVV2',
            '31'  => 'Invalid response',
            '32'  => 'Invalid format',
            '33'  => 'Invalid account',
            '34'  => 'System error',
            '35'  => 'Invalid date',
            '41'  => 'Duplicated order ID',
            '42'  => 'Transaction not found',
            '43'  => 'Verification already done',
            '44'  => 'Verification request not found',
            '45'  => 'Transaction already settled',
            '46'  => 'Settlement request not found',
            '47'  => 'Settlement already done',
            '48'  => 'Transaction already reversed',
            '49'  => 'Refund request not found',
            '412' => 'Incorrect billing information',
            '413' => 'Invalid authentication',
            '414' => 'Invalid terminal ID',
            '415' => 'Transaction in progress',
            '416' => 'Amount exceeds merchant balance',
            '417' => 'Transaction repeat limit exceeded',
            '418' => 'Unacceptable IP address',
            '419' => 'Invalid payment information',
            '421' => 'Invalid merchant signature',
            '51'  => 'Duplicated transaction',
            '54'  => 'Original transaction not found',
            '55'  => 'Invalid transaction',
            '56'  => 'Transaction system error',
            '57'  => 'Original transaction timeout',
            '58'  => 'Transaction failed',
            '61'  => 'Verification error',
        ];

        return $errors[$code] ?? "Unknown error (Code: $code)";
    }

    private function getWsdlUrl(): string
    {
        $isTest = config('payments.mellat.test_mode', true);

        return $isTest ? config('payments.mellat.test_server_url') : config('payments.mellat.server_url');
    }

    private function getGateWayUrl(): string
    {
        $isTest = config('payments.mellat.test_mode', true);

        return $isTest ? config('payments.mellat.test_gateway_url') : config('payments.mellat.gateway_url');
    }
}

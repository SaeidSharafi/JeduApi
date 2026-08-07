<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\Payment\DuplicatePaymentException;
use App\Exceptions\Payment\InvalidPaymentPurposeException;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentTransactionReferenceService;
use BadMethodCallException;
use Illuminate\Support\Facades\DB;

final class WalletPaymentProcessor implements PaymentProcessorContract
{
    public function __construct(
        private RecordWalletTransactionAction $recordWalletTransactionAction,
        private PaymentTransactionReferenceService $referenceService
    ) {}

    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::WALLET;
    }

    public function requiresRedirect(): bool
    {
        return false; // Single-step payment
    }

    public function process(Payment $payment): PaymentProcessResultData
    {
        if ($payment->purpose !== PaymentPurposeEnum::ORDER) {
            throw new InvalidPaymentPurposeException(expectedPurpose: PaymentPurposeEnum::ORDER->value, actualPurpose: $payment->purpose->value);
        }

        if ($payment->status !== PaymentStatusEnum::PENDING) {
            return PaymentProcessResultData::completed($payment);
        }

        $order       = $payment->order;
        $user        = User::query()->with('wallet')->findOrFail($order->customer_id);
        $amountToPay = (int) $payment->amount;
        $ipAddress   = request()->ip();
        $userAgent   = request()->userAgent();

        return DB::transaction(function () use ($amountToPay, $userAgent, $ipAddress, $payment, $order, $user): PaymentProcessResultData {
            $alreadyPaid = $order->payments()
                ->where('status', PaymentStatusEnum::COMPLETED)
                ->where('id', '!=', $payment->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyPaid) {
                throw new DuplicatePaymentException(paymentId: $payment->id, orderId: $order->id);
            }

            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== PaymentStatusEnum::PENDING) {
                return PaymentProcessResultData::completed($payment);
            }

            $attemptNumber = $order->payments()
                ->where('method', PaymentMethodEnum::WALLET)
                ->count() + 1;

            $transaction          = $this->referenceService->generateFor($payment);
            $transactionReference = $transaction->transaction_reference;

            $transactionData = new RecordTransactionData(
                user_id: $order->customer_id,
                type: TransactionTypeEnum::PAYMENT,
                amount: $amountToPay * -1,
                source_type: TransactionSourceEnum::ORDER,
                source_id: $order->id,
                description: $payment->admin_notes ?? __('messages.wallet.payment_for_order', ['order_id' => $order->increment_id]),
                metadata: ['order_id' => $order->id],
            );

            $availableBalance  = $user->wallet->getAvailableBalance();
            $walletTransaction = $this->recordWalletTransactionAction->execute($transactionData);

            $payment->update([
                'status'                 => PaymentStatusEnum::COMPLETED->value,
                'attempt_count'          => $attemptNumber,
                'last_attempted_at'      => now(),
                'ip_address'             => $ipAddress,
                'user_agent'             => $userAgent,
                'last_gateway_reference' => $transactionReference,
            ]);

            // Create transaction record
            $transaction->update([
                'transaction_reference' => $transactionReference,
                'attempt_number'        => $attemptNumber,
                'status'                => PaymentTransactionStatusEnum::COMPLETED,
                'gateway_request'       => [
                    'payment_method'    => 'wallet',
                    'amount'            => $amountToPay,
                    'available_balance' => $availableBalance,
                    'wallet_id'         => $user->wallet->id,
                    'order_id'          => $order->id,
                ],
                'gateway_response' => [
                    'success'     => true,
                    'new_balance' => $walletTransaction->balance_after + $walletTransaction->gift_balance_after,
                ],
                'initiated_at' => now(),
                'completed_at' => now(),
                'ip_address'   => $ipAddress,
                'user_agent'   => $userAgent,
            ]);

            PaymentCompletedEvent::dispatch($payment);

            return PaymentProcessResultData::completed($payment);
        });

    }

    /**
     * @param  array<string, mixed>  $callbackData
     */
    public function verify(Payment $payment, array $callbackData): Payment
    {
        // Not needed for single-step payments
        throw new BadMethodCallException(__('messages.payment.wallet_no_verification'));
    }
}

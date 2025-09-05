<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

final class WalletPaymentProcessor implements PaymentProcessorContract
{
    public function __construct(
        protected RecordWalletTransactionAction $recordWalletTransactionAction
    ) {
    }

    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::WALLET;
    }

    public function process(
        Order $order,
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): Payment {

        $user = User::query()->with('wallet')->findOrFail($order->customer_id);

        // Validate sufficient balance
        if ($user->wallet->getAvailableBalance() < $amountToPay) {
            throw ValidationException::withMessages([
                'wallet_data.amount' => __('validation.custom.insufficient_balance_with_info', [
                    'available' => number_format($user->wallet->balance),
                    'required'  => number_format($amountToPay),
                ]),
            ]);
        }

        // Record wallet transaction (debit)
        $transactionData = new RecordTransactionData(
            user_id: $order->customer_id,
            type: TransactionTypeEnum::PAYMENT,
            amount: $amountToPay * -1,
            source_type: TransactionSourceEnum::ORDER,
            source_id: $order->id,
            description: $walletData->description ??
            __('messages.wallet.payment_for_order', ['order_id' => $order->increment_id]),
            metadata: ['order_id' => $order->id]
        );

        $this->recordWalletTransactionAction->execute($transactionData);

        // Create payment record
        $payment = $order->payments()->create([
            'customer_id' => $order->customer_id,
            'created_by'  => $adminUser instanceof Staff ? $adminUser->id : null,
            'amount'      => (int) round($amountToPay),
            'method'      => PaymentMethodEnum::WALLET->value,
            'status'      => PaymentStatusEnum::COMPLETED->value, // Wallet payments are instant
            'admin_notes' => $paymentData->admin_notes,
            'data'        => []
        ]);

        // Dispatch completion event for wallet payments (they're always completed)
        PaymentCompletedEvent::dispatch($payment);

        return $payment;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\Payment\InsufficientWalletBalanceException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\User;
use App\Services\PaymentTransactionReferenceService;
use BadMethodCallException;
use Illuminate\Contracts\Auth\Authenticatable;

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

    public function process(
        Order $order,
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): PaymentProcessResultData {

        $user = User::query()->with('wallet')->findOrFail($order->customer_id);

        // Step 1: Generate unique transaction reference
        $transactionReference = $this->referenceService->generate();

        // Step 2: Get request metadata
        $ipAddress = request()->ip();
        $userAgent = request()->userAgent();

        // Step 3: Calculate attempt number
        $attemptNumber = $order->payments()
            ->where('method', PaymentMethodEnum::WALLET)
            ->count() + 1;

        // Validate sufficient balance
        $availableBalance = $user->wallet->getAvailableBalance();
        if ($availableBalance < $amountToPay) {
            $shortfall = $amountToPay - $availableBalance;
            throw new InsufficientWalletBalanceException(
                availableBalance: $availableBalance,
                requiredBalance: $amountToPay,
                shortfall: $shortfall,
                orderIncrementId: (string) $order->increment_id
            );
        }

        // Record wallet transaction (debit)
        $transactionData = new RecordTransactionData(
            user_id: $order->customer_id,
            type: TransactionTypeEnum::PAYMENT,
            amount: $amountToPay * -1,
            source_type: TransactionSourceEnum::ORDER,
            source_id: $order->id,
            description: $paymentData->admin_notes ?? __('messages.wallet.payment_for_order', ['order_id' => $order->increment_id]),
            metadata: ['order_id' => $order->id]
        );

        $this->recordWalletTransactionAction->execute($transactionData);

        // Create payment record
        $payment = $order->payments()->create([
            'customer_id'       => $order->customer_id,
            'created_by'        => $adminUser instanceof Staff ? $adminUser->id : null,
            'amount'            => (int) round($amountToPay),
            'method'            => PaymentMethodEnum::WALLET->value,
            'status'            => PaymentStatusEnum::COMPLETED->value,
            'admin_notes'       => $paymentData->admin_notes,
            'attempt_count'     => $attemptNumber,
            'last_attempted_at' => now(),
            'ip_address'        => $ipAddress,
            'user_agent'        => $userAgent,
        ]);

        // Create transaction record
        $payment->transactions()->create([
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
                'new_balance' => $availableBalance - $amountToPay,
            ],
            'initiated_at' => now(),
            'completed_at' => now(),
            'ip_address'   => $ipAddress,
            'user_agent'   => $userAgent,
        ]);

        // Update payment with last reference for quick access
        $payment->last_gateway_reference = $transactionReference;
        $payment->save();

        // Dispatch completion event for wallet payments (they're always completed)
        PaymentCompletedEvent::dispatch($payment);

        return PaymentProcessResultData::completed($payment);
    }

    public function verify(Payment $payment, array $callbackData): Payment
    {
        // Not needed for single-step payments
        throw new BadMethodCallException('Wallet payments do not require verification');
    }
}

<?php

declare(strict_types=1);

use App\Actions\Shop\Wallet\TopupWalletAction;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;

it('does not double-credit wallet on replayed topup handling', function (): void {
    $user = User::factory()->create();

    $payment = Payment::factory()->topup()->create([
        'customer_id' => $user->id,
        'status'      => PaymentStatusEnum::COMPLETED,
        'method'      => PaymentMethodEnum::MELLAT_GATEWAY,
        'amount'      => 250000,
    ]);

    $action = app(TopupWalletAction::class);

    $action->handle($payment);
    $action->handle($payment);

    $user->wallet->refresh();
    expect($user->wallet->balance)->toBe(250000);

    $rows = WalletTransaction::query()
        ->where('user_id', $user->id)
        ->where('type', TransactionTypeEnum::DEPOSIT)
        ->where('source_type', TransactionSourceEnum::DEPOSIT)
        ->where('source_id', $payment->id)
        ->count();

    expect($rows)->toBe(1);
});

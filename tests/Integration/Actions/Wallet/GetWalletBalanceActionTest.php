<?php

declare(strict_types=1);

use App\Actions\Admin\Wallet\GetWalletBalanceAction;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

test('get wallet balance returns correct data', function (): void {
    $user = User::factory()->create();
    $user->wallet->update(['balance' => 1500, 'gift_balance' => 500]);

    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_VIEW_ANY,
    ]);

    $balance = (new GetWalletBalanceAction())->execute($user->id);

    expect($balance)->toBe([
        'user_id'           => $user->id,
        'balance'           => 1500,
        'gift_balance'      => 500,
        'available_balance' => 2000,
        'status'            => WalletStatusEnum::ACTIVE,
    ]);
});

test('cannot get balance for invalid user', function (): void {
    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_VIEW_ANY,
    ]);

    expect(fn (): array => (new GetWalletBalanceAction())->execute(999999))
        ->toThrow(Exception::class, __('validation.custom.user_not_found'));
});

test('cannot get balance for user without wallet', function (): void {
    $user = User::factory()->create();
    $user->wallet()->delete();

    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_VIEW_ANY,
    ]);

    expect(fn (): array => (new GetWalletBalanceAction())->execute($user->id))
        ->toThrow(Exception::class, __('validation.custom.wallet_not_found'));
});

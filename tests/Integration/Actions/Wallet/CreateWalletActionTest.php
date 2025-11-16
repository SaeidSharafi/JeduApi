<?php

declare(strict_types=1);

use App\Actions\Admin\Wallet\CreateWalletAction;
use App\Data\Admin\Wallet\CreateWalletData;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use App\Models\Wallet;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->action = new CreateWalletAction();
});
test('wallet is auto-created for new user', function (): void {
    $user = User::factory()->create();
    expect($user->wallet)->not->toBeNull()
        ->and($user->wallet->user_id)->toBe($user->id)
        ->and($user->wallet->balance)->toBe(0)
        ->and($user->wallet->gift_balance)->toBe(0)
        ->and($user->wallet->status)->toBe(WalletStatusEnum::ACTIVE);
});
test('cannot create duplicate wallet for user', function (): void {
    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_CREATE,
    ]);
    $user = User::factory()->create();
    $data = CreateWalletData::from([
        'user_id'      => $user->id,
        'balance'      => 500,
        'gift_balance' => 0,
        'status'       => WalletStatusEnum::ACTIVE->value,
    ]);
    expect(fn () => $this->action->execute($data))
        ->toThrow(Exception::class, __('validation.wallet_already_exists'));
});

test('cannot create wallet for invalid user', function (): void {
    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_CREATE,
    ]);
    $data = CreateWalletData::from([
        'user_id'      => 999999,
        'balance'      => 100,
        'gift_balance' => 0,
        'status'       => WalletStatusEnum::ACTIVE->value,
    ]);
    expect(fn () => $this->action->execute($data))
        ->toThrow(Exception::class, __('validation.custom.user_not_found'));
});

it('successfully creates a wallet for user', function (): void {
    $user = User::factory()->create();
    $user->wallet->delete(); // Remove auto-created wallet

    $data = new CreateWalletData(
        user_id: $user->id,
        balance: 50000,
        gift_balance: 25000,
        status: WalletStatusEnum::ACTIVE->value
    );

    $wallet = $this->action->execute($data);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->user_id)->toBe($user->id);
    expect($wallet->balance)->toBe(50000);
    expect($wallet->gift_balance)->toBe(25000);
    expect($wallet->status)->toBe(WalletStatusEnum::ACTIVE);
    expect($wallet->exists)->toBeTrue();

    // Verify wallet is persisted in database
    $this->assertDatabaseHas('wallets', [
        'user_id'      => $user->id,
        'balance'      => 50000,
        'gift_balance' => 25000,
        'status'       => WalletStatusEnum::ACTIVE->value,
    ]);
});

it('creates wallet with zero balances by default', function (): void {
    $user = User::factory()->create();
    $user->wallet->delete();

    $data = new CreateWalletData(
        user_id: $user->id,
        balance: 0,
        gift_balance: 0,
        status: WalletStatusEnum::ACTIVE->value
    );

    $wallet = $this->action->execute($data);

    expect($wallet->balance)->toBe(0);
    expect($wallet->gift_balance)->toBe(0);
});

it('creates wallet with suspended status', function (): void {
    $user = User::factory()->create();
    $user->wallet->delete();

    $data = new CreateWalletData(
        user_id: $user->id,
        balance: 10000,
        gift_balance: 5000,
        status: WalletStatusEnum::SUSPENDED->value
    );

    $wallet = $this->action->execute($data);

    expect($wallet->status)->toBe(WalletStatusEnum::SUSPENDED);
});

it('throws exception when user does not exist', function (): void {
    $data = new CreateWalletData(
        user_id: 99999, // Non-existent user
        balance: 10000,
        gift_balance: 5000,
        status: WalletStatusEnum::ACTIVE->value
    );

    expect(fn () => $this->action->execute($data))
        ->toThrow(Exception::class, __('validation.custom.user_not_found'));
});

it('throws exception when user already has a wallet', function (): void {
    $user = User::factory()->create(); // This creates a wallet automatically

    $data = new CreateWalletData(
        user_id: $user->id,
        balance: 10000,
        gift_balance: 5000,
        status: WalletStatusEnum::ACTIVE->value
    );

    expect(fn () => $this->action->execute($data))
        ->toThrow(Exception::class, __('validation.wallet_already_exists'));
});

it('handles large balance amounts correctly', function (): void {
    $user = User::factory()->create();
    $user->wallet->delete();

    $largeBalance     = 999999999; // Large amount
    $largeGiftBalance = 888888888;

    $data = new CreateWalletData(
        user_id: $user->id,
        balance: $largeBalance,
        gift_balance: $largeGiftBalance,
        status: WalletStatusEnum::ACTIVE->value
    );

    $wallet = $this->action->execute($data);

    expect($wallet->balance)->toBe($largeBalance);
    expect($wallet->gift_balance)->toBe($largeGiftBalance);
});

it('creates wallet with all valid status enums', function (): void {
    foreach (WalletStatusEnum::cases() as $status) {
        $user = User::factory()->create();
        $user->wallet->delete();

        $data = new CreateWalletData(
            user_id: $user->id,
            balance: 10000,
            gift_balance: 5000,
            status: $status->value
        );

        $wallet = $this->action->execute($data);

        expect($wallet->status)->toBe($status);
    }
});

it('properly associates wallet with user relationship', function (): void {
    $user = User::factory()->create();
    $user->wallet->delete();

    $data = new CreateWalletData(
        user_id: $user->id,
        balance: 15000,
        gift_balance: 7500,
        status: WalletStatusEnum::ACTIVE->value
    );

    $wallet = $this->action->execute($data);

    // Test relationship
    expect($wallet->user->id)->toBe($user->id);
    expect($user->fresh()->wallet->id)->toBe($wallet->id);
});

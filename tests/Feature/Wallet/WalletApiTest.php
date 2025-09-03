<?php

declare(strict_types=1);

use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

test('admin can deposit to wallet via API', function () {
    $admin = $this->authorized_user([
        \App\Enums\PermissionEnum::WALLET_DEPOSIT
    ]);

    $user = User::factory()->create();
    $initialBalance = $user->wallet->balance;

    $response = $this
        ->postJson('/api/v1/admin/wallet/deposit', [
            'user_id'     => $user->id,
            'amount'      => 1000,
            'description' => 'API deposit test',
        ]);

    $response->assertStatus(201);

    $response->assertJsonStructure([
        'message',
        'data' => [
            'wallet'      => [
                'balance',
                'gift_balance',
                'status' => [
                    'value',
                    'label',
                ],
                'user',
            ],
            'user'        => [
                'id',
                'uuid',
                'first_name',
                'last_name',
                'phone',
                'phone2',
                'phone_verified_at',
                'email',
                'email_verified_at',
                'civil_id',
                'civil_id_type',
                'date_of_birth',
                'father_name',
                'gender',
                'education_level',
                'field_of_study',
                'education_status',
                'created_at',
                'updated_at',
            ],
            'type'        => [
                'value',
                'label',
            ],
            'amount',
            'balance_after',
            'gift_balance_after',
            'source_type' => [
                'value',
                'label',
            ],
            'source'      => [
                'id',
                'name',
                'email',
                'phone',
                'is_admin',
                'roles',
            ],
            'description',
            'metadata',
            'expires_at',
            'created_at',
        ],
        'metadata',
    ]);
    $user->refresh();
    expect($user->wallet->balance)->toBe($initialBalance + 1000);

});

test('admin cannot deposit to wallet without permission', function () {
    $admin = $this->authorized_user([]);
    $user = User::factory()->create();

    $response = $this
        ->postJson('/api/v1/admin/wallet/deposit', [
            'user_id' => $user->id,
            'amount'  => 1000,
        ]);

    $response->assertStatus(403);
});

test('admin can withdraw from wallet via API', function () {
    $admin = $this->authorized_user([
        \App\Enums\PermissionEnum::WALLET_WITHDRAWAL
    ]);

    $user = User::factory()->create();
    $user->wallet->update(['balance' => 2000]);

    $response = $this
        ->postJson('/api/v1/admin/wallet/withdrawal', [
            'user_id'     => $user->id,
            'amount'      => 500,
            'description' => 'API withdrawal test',
        ]);

    $response->assertStatus(201);

    $user->refresh();
    expect($user->wallet->balance)->toBe(1500);
});

test('admin cannot withdraw more than available balance via API', function () {
    $admin = $this->authorized_user([
        \App\Enums\PermissionEnum::WALLET_WITHDRAWAL
    ]);

    $user = User::factory()->create();
    $user->wallet->update(['balance' => 100]);

    $response = $this
        ->postJson('/api/v1/admin/wallet/withdrawal', [
            'user_id' => $user->id,
            'amount'  => 500,
        ]);

    $response->assertStatus(500); // Should return validation error
});

test('validation errors are returned for invalid data', function () {
    $admin = $this->authorized_user([
        \App\Enums\PermissionEnum::WALLET_DEPOSIT
    ]);

    $response = $this
        ->postJson('/api/v1/admin/wallet/deposit', [
            'user_id' => 999999, // Invalid user
            'amount'  => -100, // Negative amount
        ]);

    $response->assertStatus(422);
});

test('admin can adjust wallet via API', function () {
    $this->authorized_user([\App\Enums\PermissionEnum::WALLET_ADJUSTMENT]);

    $user = User::factory()->create();
    $user->wallet->update(['balance' => 1000]);
    $initialBalance = $user->wallet->balance;

    $response = $this
        ->postJson('/api/v1/admin/wallet/adjustment', [
            'user_id'     => $user->id,
            'amount'      => 300,
            'reason'      => 'Dispute resolution',
            'description' => 'API adjustment test',
        ]);

    $response->assertStatus(201);

    $user->refresh();
    expect($user->wallet->balance)->toBe($initialBalance + 300);
});

test('admin can make negative adjustment via API', function () {
    $this->authorized_user([\App\Enums\PermissionEnum::WALLET_ADJUSTMENT]);

    $user = User::factory()->create();
    $user->wallet->update(['balance' => 1000]);
    $initialBalance = $user->wallet->balance;

    $response = $this
        ->postJson('/api/v1/admin/wallet/adjustment', [
            'user_id'     => $user->id,
            'amount'      => -200,
            'reason'      => 'Error correction',
            'description' => 'API negative adjustment test',
        ]);

    $response->assertStatus(201);

    $user->refresh();
    expect($user->wallet->balance)->toBe($initialBalance - 200);
});

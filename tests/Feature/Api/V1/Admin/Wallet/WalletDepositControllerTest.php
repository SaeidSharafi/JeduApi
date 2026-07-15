<?php

declare(strict_types=1);

use App\Models\User;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

test('admin can deposit to wallet via API', function (): void {
    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_DEPOSIT,
    ]);

    $user           = User::factory()->create();
    $initialBalance = $user->wallet->balance;

    $response = $this
        ->postJson(route('api.v1.admin.users.wallet.deposit', $user->wallet->id), [
            'user_id'     => $user->id,
            'amount'      => 1000,
            'description' => 'API deposit test',
        ]);

    $response->assertStatus(201);

    $response->assertJsonStructure([
        'message',
        'data' => [
            'id',
            'wallet' => [
                'balance',
                'gift_balance',
                'status' => [
                    'value',
                    'label',
                ],
            ],
            'user' => [
                'id',
                'first_name',
                'last_name',
                'phone',
                'phone2',
                'email',
                'civil_id',
                'civil_id_type',
                'date_of_birth',
                'father_name',
                'gender',
                'education_level',
                'field_of_study',
                'education_status',
            ],
            'type' => [
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
            'source' => [
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

test('admin cannot deposit to wallet without permission', function (): void {
    $admin = $this->authorized_user([]);
    $user  = User::factory()->create();

    $response = $this
        ->postJson(route('api.v1.admin.users.wallet.deposit', $user->wallet->id), [
            'user_id' => $user->id,
            'amount'  => 1000,
        ]);

    $response->assertStatus(403);
});

test('validation errors are returned for invalid data', function (): void {
    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_DEPOSIT,
    ]);
    $user     = User::factory()->create();
    $response = $this
        ->postJson(route('api.v1.admin.users.wallet.deposit', $user->wallet->id), [
            'amount' => 'A100', // Negative amount
        ]);

    $response->assertStatus(422);
});

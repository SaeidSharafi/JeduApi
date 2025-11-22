<?php

declare(strict_types=1);

use App\Models\User;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

test('admin can adjust wallet via API', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::WALLET_ADJUSTMENT]);

    $user = User::factory()->create();
    $user->wallet->update(['balance' => 1000]);
    $initialBalance = $user->wallet->balance;

    $response = $this
        ->postJson(route('api.v1.admin.wallet.adjustment', $user->wallet->id), [
            'user_id'     => $user->id,
            'amount'      => 300,
            'reason'      => 'Dispute resolution',
            'description' => 'API adjustment test',
        ]);

    $response->assertStatus(201);

    $user->refresh();
    expect($user->wallet->balance)->toBe($initialBalance + 300);
});

test('admin can make negative adjustment via API', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::WALLET_ADJUSTMENT]);

    $user = User::factory()->create();
    $user->wallet->update(['balance' => 1000]);
    $initialBalance = $user->wallet->balance;

    $response = $this
        ->postJson(route('api.v1.admin.wallet.adjustment', $user->wallet->id), [
            'user_id'     => $user->id,
            'amount'      => -200,
            'reason'      => 'Error correction',
            'description' => 'API negative adjustment test',
        ]);

    $response->assertStatus(201);

    $user->refresh();
    expect($user->wallet->balance)->toBe($initialBalance - 200);
});

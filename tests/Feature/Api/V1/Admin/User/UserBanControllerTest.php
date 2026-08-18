<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('ban', function (): void {
    it('bans a customer and revokes all active tokens', function (): void {
        $this->authorized_user([PermissionEnum::USER_BAN]);

        $user  = User::factory()->create();
        $token = $user->createToken('auth_token', ['*'], now()->addMinutes(60));

        $response = $this->postJson(route('api.v1.admin.users.ban', $user->id));

        $response->assertOk();
        $response->assertJson(function (AssertableJson $json) use ($user): void {
            $json->where('data.id', $user->id)
                ->where('data.is_banned', true)
                ->whereNot('data.banned_at', null)
                ->etc();
        });

        $this->assertDatabaseHas('users', [
            'id'        => $user->id,
            'is_banned' => true,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        // the revoked token can no longer authenticate
        $this->withToken($token->plainTextToken)
            ->getJson(route('api.v1.shop.wallet.info'))
            ->assertUnauthorized();
    });

    it('is idempotent when the customer is already banned', function (): void {
        $this->authorized_user([PermissionEnum::USER_BAN]);

        $user = User::factory()->create([
            'is_banned' => true,
            'banned_at' => now(),
        ]);

        $this->postJson(route('api.v1.admin.users.ban', $user->id))
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id'        => $user->id,
            'is_banned' => true,
        ]);
    });

    it('requires the users.ban permission', function (): void {
        $this->unauthorized_user();

        $user = User::factory()->create();

        $this->postJson(route('api.v1.admin.users.ban', $user->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id'        => $user->id,
            'is_banned' => false,
        ]);
    });

    it('blocks login while banned and restores it after unban', function (): void {
        $this->authorized_user([PermissionEnum::USER_BAN]);

        $user = User::factory()->create([
            'email'    => 'customer@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson(route('api.v1.admin.users.ban', $user->id))
            ->assertOk();

        $this->postJson(route('api.v1.auth.password-login'), [
            'identifier' => 'customer@example.com',
            'type'       => 'email',
            'password'   => 'password123',
        ])->assertForbidden();

        $this->postJson(route('api.v1.admin.users.unban', $user->id))
            ->assertOk();

        $this->postJson(route('api.v1.auth.password-login'), [
            'identifier' => 'customer@example.com',
            'type'       => 'email',
            'password'   => 'password123',
        ])->assertOk();
    });
});

describe('unban', function (): void {
    it('unbans a customer and clears the ban state', function (): void {
        $this->authorized_user([PermissionEnum::USER_BAN]);

        $user = User::factory()->create([
            'is_banned' => true,
            'banned_at' => now(),
        ]);

        $response = $this->postJson(route('api.v1.admin.users.unban', $user->id));

        $response->assertOk();
        $response->assertJson(function (AssertableJson $json) use ($user): void {
            $json->where('data.id', $user->id)
                ->where('data.is_banned', false)
                ->where('data.banned_at', null)
                ->etc();
        });

        $this->assertDatabaseHas('users', [
            'id'        => $user->id,
            'is_banned' => false,
            'banned_at' => null,
        ]);
    });

    it('is idempotent when the customer is not banned', function (): void {
        $this->authorized_user([PermissionEnum::USER_BAN]);

        $user = User::factory()->create();

        $this->postJson(route('api.v1.admin.users.unban', $user->id))
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id'        => $user->id,
            'is_banned' => false,
        ]);
    });

    it('requires the users.ban permission', function (): void {
        $this->unauthorized_user();

        $user = User::factory()->create([
            'is_banned' => true,
        ]);

        $this->postJson(route('api.v1.admin.users.unban', $user->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id'        => $user->id,
            'is_banned' => true,
        ]);
    });
});

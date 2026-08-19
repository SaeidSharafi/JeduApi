<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('ban', function (): void {
    it('bans a staff account and revokes all active tokens', function (): void {
        $this->authorized_user([PermissionEnum::STAFF_BAN]);

        $staff = Staff::factory()->create();
        $token = $staff->createToken('auth_token', ['*'], now()->addMinutes(60));

        $response = $this->postJson(route('api.v1.admin.staff.ban', $staff->id));

        $response->assertOk();
        $response->assertJson(function (AssertableJson $json) use ($staff): void {
            $json->where('data.id', $staff->id)
                ->where('data.is_banned', true)
                ->whereNot('data.banned_at', null)
                ->etc();
        });

        $this->assertDatabaseHas('staff', [
            'id'        => $staff->id,
            'is_banned' => true,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $staff->id,
        ]);

        // the revoked token can no longer authenticate
        auth('staff')->forgetUser();
        $this->withToken($token->plainTextToken)
            ->getJson(route('api.v1.admin.profile.show'))
            ->assertUnauthorized();
    });

    it('is idempotent when the staff account is already banned', function (): void {
        $this->authorized_user([PermissionEnum::STAFF_BAN]);

        $staff = Staff::factory()->create([
            'is_banned' => true,
            'banned_at' => now(),
        ]);

        $this->postJson(route('api.v1.admin.staff.ban', $staff->id))
            ->assertOk();

        $this->assertDatabaseHas('staff', [
            'id'        => $staff->id,
            'is_banned' => true,
        ]);
    });

    it('requires the staff.ban permission', function (): void {
        $this->unauthorized_user();

        $staff = Staff::factory()->create();

        $this->postJson(route('api.v1.admin.staff.ban', $staff->id))
            ->assertForbidden();

        $this->assertDatabaseHas('staff', [
            'id'        => $staff->id,
            'is_banned' => false,
        ]);
    });

    it('blocks login while banned and restores it after unban', function (): void {
        $this->authorized_user([PermissionEnum::STAFF_BAN]);

        $staff = Staff::factory()->create([
            'email'    => 'staff@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson(route('api.v1.admin.staff.ban', $staff->id))
            ->assertOk();

        $this->postJson(route('api.v1.admin.auth.password-login'), [
            'identifier' => 'staff@example.com',
            'type'       => 'email',
            'password'   => 'password123',
        ])->assertForbidden();

        $this->postJson(route('api.v1.admin.staff.unban', $staff->id))
            ->assertOk();

        $this->postJson(route('api.v1.admin.auth.password-login'), [
            'identifier' => 'staff@example.com',
            'type'       => 'email',
            'password'   => 'password123',
        ])->assertOk();
    });
});

describe('unban', function (): void {
    it('unbans a staff account and clears the ban state', function (): void {
        $this->authorized_user([PermissionEnum::STAFF_BAN]);

        $staff = Staff::factory()->create([
            'is_banned' => true,
            'banned_at' => now(),
        ]);

        $response = $this->postJson(route('api.v1.admin.staff.unban', $staff->id));

        $response->assertOk();
        $response->assertJson(function (AssertableJson $json) use ($staff): void {
            $json->where('data.id', $staff->id)
                ->where('data.is_banned', false)
                ->where('data.banned_at', null)
                ->etc();
        });

        $this->assertDatabaseHas('staff', [
            'id'        => $staff->id,
            'is_banned' => false,
            'banned_at' => null,
        ]);
    });

    it('is idempotent when the staff account is not banned', function (): void {
        $this->authorized_user([PermissionEnum::STAFF_BAN]);

        $staff = Staff::factory()->create();

        $this->postJson(route('api.v1.admin.staff.unban', $staff->id))
            ->assertOk();

        $this->assertDatabaseHas('staff', [
            'id'        => $staff->id,
            'is_banned' => false,
        ]);
    });

    it('requires the staff.ban permission', function (): void {
        $this->unauthorized_user();

        $staff = Staff::factory()->create([
            'is_banned' => true,
        ]);

        $this->postJson(route('api.v1.admin.staff.unban', $staff->id))
            ->assertForbidden();

        $this->assertDatabaseHas('staff', [
            'id'        => $staff->id,
            'is_banned' => true,
        ]);
    });
});

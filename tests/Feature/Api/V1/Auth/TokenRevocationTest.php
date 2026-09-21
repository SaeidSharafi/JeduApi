<?php

declare(strict_types=1);

use App\Actions\Admin\Staff\BanStaffAction;
use App\Actions\Admin\Staff\DeleteStaffAction;
use App\Actions\Admin\User\BanUserAction;
use App\Actions\Admin\User\DeleteUserAction;
use App\Enums\PermissionEnum;
use App\Models\PersonalAccessToken;
use App\Models\Staff;
use App\Models\User;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(
    BanUserAction::class,
    DeleteUserAction::class,
    BanStaffAction::class,
    DeleteStaffAction::class,
    PersonalAccessToken::class,
);

/**
 * Every case here warms the token caches with a successful request first:
 * without the fix, the cached lookup keeps authenticating a revoked token
 * until its ttl expires, so a test that never warms the cache passes either way.
 *
 * Feature requests in one test share the container, so the guard keeps the
 * resolved user between requests; `forgetUser()` forces each request to
 * resolve the bearer token again, as a fresh request would in production.
 */
it('rejects a banned customer bearer token that was already cached', function (): void {
    $this->authorized_user([PermissionEnum::USER_BAN]);

    $user  = User::factory()->create();
    $token = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.shop.wallet.info'))
        ->assertOk();

    $this->postJson(route('api.v1.admin.users.ban', $user->id))->assertOk();

    auth('user')->forgetUser();
    $this->withToken($token)
        ->getJson(route('api.v1.shop.wallet.info'))
        ->assertUnauthorized();
});

it('rejects a deleted customer bearer token that was already cached', function (): void {
    $this->authorized_user([PermissionEnum::USER_DELETE]);

    $user  = User::factory()->create();
    $token = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.shop.wallet.info'))
        ->assertOk();

    $this->deleteJson(route('api.v1.admin.users.destroy', $user->id))->assertNoContent();

    auth('user')->forgetUser();
    $this->withToken($token)
        ->getJson(route('api.v1.shop.wallet.info'))
        ->assertUnauthorized();
});

it('rejects a banned staff bearer token that was already cached', function (): void {
    $this->authorized_user([PermissionEnum::STAFF_BAN]);

    $staff = Staff::factory()->create();
    $token = $staff->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;

    // The admin is authenticated on the same staff guard, so drop the acting
    // user before warming the target staff member's own token.
    auth('staff')->forgetUser();
    $this->withToken($token)
        ->getJson(route('api.v1.admin.profile.show'))
        ->assertOk();

    $this->authorized_user([PermissionEnum::STAFF_BAN]);
    $this->postJson(route('api.v1.admin.staff.ban', $staff->id))->assertOk();

    auth('staff')->forgetUser();
    $this->withToken($token)
        ->getJson(route('api.v1.admin.profile.show'))
        ->assertUnauthorized();
});

it('rejects a deleted staff bearer token that was already cached', function (): void {
    $this->authorized_user([PermissionEnum::STAFF_DELETE]);

    $staff = Staff::factory()->create();
    $token = $staff->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;

    auth('staff')->forgetUser();
    $this->withToken($token)
        ->getJson(route('api.v1.admin.profile.show'))
        ->assertOk();

    $this->authorized_user([PermissionEnum::STAFF_DELETE]);
    $this->deleteJson(route('api.v1.admin.staff.destroy', $staff->id))->assertNoContent();

    auth('staff')->forgetUser();
    $this->withToken($token)
        ->getJson(route('api.v1.admin.profile.show'))
        ->assertUnauthorized();
});

it('logging out invalidates only the used token', function (): void {
    $user   = User::factory()->create();
    $first  = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;
    $second = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;

    auth('user')->forgetUser();
    $this->withToken($first)
        ->getJson(route('api.v1.shop.wallet.info'))
        ->assertOk();

    auth('user')->forgetUser();
    $this->withToken($second)
        ->getJson(route('api.v1.shop.wallet.info'))
        ->assertOk();

    auth('user')->forgetUser();
    $this->withToken($first)
        ->postJson(route('api.v1.auth.logout'))
        ->assertNoContent();

    auth('user')->forgetUser();
    $this->withToken($first)
        ->getJson(route('api.v1.shop.wallet.info'))
        ->assertUnauthorized();

    auth('user')->forgetUser();
    $this->withToken($second)
        ->getJson(route('api.v1.shop.wallet.info'))
        ->assertOk();
});

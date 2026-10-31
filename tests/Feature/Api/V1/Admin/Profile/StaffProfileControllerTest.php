<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Models\PersonalAccessToken;
use App\Models\Staff;
use Spatie\Permission\Models\Role;

it('returns the access fields the admin panel checks', function (): void {
    $staff = Staff::factory()->isSuperAdmin()->create();
    $token = $staff->createToken('staff_token')->plainTextToken;

    $response = $this->withCredentials()
        ->withCookie('staff_token', $token)
        ->getJson(route('api.v1.admin.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.is_admin', true)
        ->assertJsonPath('data.roles', []);

    expect($response->json('data.permissions'))
        ->toContain('staff.view')
        ->toContain('roles.view_any');
});

it('returns the roles and permissions of a regular staff member', function (): void {
    $staff = Staff::factory()->create();
    $role  = Role::create(['name' => 'editor', 'label' => 'Editor', 'guard_name' => 'staff']);
    $role->syncPermissions(['staff.view']);
    $staff->assignRole($role);

    $token = $staff->createToken('staff_token')->plainTextToken;

    $this->withCredentials()
        ->withCookie('staff_token', $token)
        ->getJson(route('api.v1.admin.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.is_admin', false)
        ->assertJsonPath('data.roles', ['editor'])
        ->assertJsonPath('data.permissions', ['staff.view']);
});

it('shows staff profile using the http only authentication cookie', function (): void {
    $staff = Staff::factory()->create();
    $token = $staff->createToken('staff_token')->plainTextToken;

    $this->withCredentials()
        ->withCookie('staff_token', $token)
        ->getJson(route('api.v1.admin.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $staff->id);
});

it('rejects unauthenticated staff profile requests', function (): void {
    $this->getJson(route('api.v1.admin.profile.show'))
        ->assertUnauthorized();
});

it('clears the cached tokenable when the staff profile is updated', function (): void {
    $staff = Staff::factory()->create();
    $plain = $staff->createToken('staff_token', ['*'], now()->addMinutes(60))->plainTextToken;
    $token = PersonalAccessToken::findToken($plain);
    $token->tokenable;

    $this->withToken($plain)
        ->putJson(route('api.v1.admin.profile.update'), [
            'name'  => 'Updated Staff',
            'email' => 'updated.staff@example.com',
            'phone' => $staff->phone,
        ])
        ->assertOk();

    expect(app(CacheStore::class)->get(CacheKey::Tokenable, [
        'id'  => $token->id,
        'env' => app()->environment(),
    ]))->toBeNull();
});

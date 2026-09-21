<?php

declare(strict_types=1);

use App\Enums\System\OtpType;
use App\Http\Controllers\Api\Admin\Auth\StaffOtpAuthenticationController;
use App\Http\Controllers\Api\Admin\Auth\StaffPasswordLoginController;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

covers(StaffPasswordLoginController::class, StaffOtpAuthenticationController::class);

/**
 * The staff permission list is read from the database on every login, so a
 * permission added after the previous login is never served from a stale cache.
 * These tests pin that freshness on both staff login paths.
 */
it('returns permissions added since the previous password login', function (): void {
    Staff::factory()->create([
        'email'    => 'staff@example.com',
        'password' => Hash::make('password123'),
    ]);

    $credentials = [
        'identifier' => 'staff@example.com',
        'type'       => 'email',
        'password'   => 'password123',
    ];

    $first = $this->postJson('/api/v1/admin/auth/login/password', $credentials)->assertOk();

    expect($first->json('data.permissions'))->not->toContain('staff.jedi-master');

    Permission::create(['name' => 'staff.jedi-master', 'guard_name' => 'staff']);
    Role::create(['name' => 'test-role', 'label' => 'Test Role', 'guard_name' => 'staff'])
        ->syncPermissions(['staff.jedi-master']);

    $second = $this->postJson('/api/v1/admin/auth/login/password', $credentials)->assertOk();

    expect($second->json('data.permissions'))->toContain('staff.jedi-master');
});

it('returns permissions added since the previous otp login', function (): void {
    Staff::factory()->create(['phone' => '09301234567']);

    $first = $this->postJson('/api/v1/admin/auth/otp/verify', otpLoginPayload('09301234567'))->assertOk();

    expect($first->json('data.permissions'))->not->toContain('staff.jedi-master');

    Permission::create(['name' => 'staff.jedi-master', 'guard_name' => 'staff']);
    Role::create(['name' => 'test-role', 'label' => 'Test Role', 'guard_name' => 'staff'])
        ->syncPermissions(['staff.jedi-master']);

    $second = $this->postJson('/api/v1/admin/auth/otp/verify', otpLoginPayload('09301234567'))->assertOk();

    expect($second->json('data.permissions'))->toContain('staff.jedi-master');
});

it('returns the same permission list from both staff login paths', function (): void {
    Staff::factory()->create([
        'email'    => 'staff@example.com',
        'password' => Hash::make('password123'),
    ]);
    Staff::factory()->create(['phone' => '09301234567']);

    $passwordLogin = $this->postJson('/api/v1/admin/auth/login/password', [
        'identifier' => 'staff@example.com',
        'type'       => 'email',
        'password'   => 'password123',
    ])->assertOk();

    $otpLogin = $this->postJson('/api/v1/admin/auth/otp/verify', otpLoginPayload('09301234567'))->assertOk();

    expect($otpLogin->json('data.permissions'))
        ->toEqual($passwordLogin->json('data.permissions'))
        ->toContain('staff.view');
});

/**
 * Seed a verifiable OTP for the phone and build the staff verify payload.
 *
 * @return array<string, mixed>
 */
function otpLoginPayload(string $phone): array
{
    $code         = random_int((int) config('otp.code_min'), (int) config('otp.code_max'));
    $trackingCode = 'test-tracking';

    putCachedOtp($phone, 'staff', OtpType::SIGNIN, $code, $trackingCode);

    return [
        'identifier'    => $phone,
        'type'          => 'phone',
        'otp_code'      => $code,
        'tracking_code' => $trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ];
}

<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\Enrollment;
use App\Models\ProvisioningAttempt;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

function recoveryEnrollment(string $provider = 'skyroom'): Enrollment
{
    $enrollment = Enrollment::factory()->create();
    $enrollment->update([
        'provisioning_plan' => ['version' => 1, 'providers' => [['provider' => $provider, 'applicable' => true, 'readiness' => 'ready']], 'status' => 'manual_action_required'],
        'provisioning_data' => ['providers' => [$provider => ['status' => 'manual_action_required']]],
    ]);

    return $enrollment->fresh();
}

it('requires the dedicated waiver permission', function (): void {
    $enrollment = recoveryEnrollment();
    $this->authorized_user([PermissionEnum::ENROLLMENT_RETRY_PROVISION->value]);

    $this->postJson("/api/v1/admin/enrollments/{$enrollment->id}/provisioning/waive", [
        'provider' => 'skyroom',
        'reason'   => 'Approved exception.',
    ])->assertForbidden();
});

it('validates confirmation and records a manual resolution through the API', function (): void {
    $enrollment = recoveryEnrollment();
    $this->authorized_user([PermissionEnum::ENROLLMENT_RETRY_PROVISION->value]);

    $this->postJson("/api/v1/admin/enrollments/{$enrollment->id}/provisioning-plan/apply", [
        'confirm' => false,
    ])->assertUnprocessable();

    $this->postJson("/api/v1/admin/enrollments/{$enrollment->id}/provisioning/resolve", [
        'provider'   => 'skyroom',
        'references' => ['room_id' => 'not-an-id'],
        'reason'     => 'Verified manually.',
    ])->assertUnprocessable();

    $this->postJson("/api/v1/admin/enrollments/{$enrollment->id}/provisioning/resolve", [
        'provider'   => 'skyroom',
        'references' => ['room_id' => 42],
        'reason'     => 'Verified manually.',
    ])->assertOk();

    expect(ProvisioningAttempt::query()->where('enrollment_id', $enrollment->id)->count())->toBe(1);
});

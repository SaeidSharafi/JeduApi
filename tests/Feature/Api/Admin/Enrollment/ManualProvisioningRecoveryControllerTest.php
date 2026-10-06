<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
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

it('previews a rebuilt plan and applies it after confirmation', function (): void {
    $enrollment = Enrollment::factory()->create();
    $enrollment->update([
        'provisioning_plan' => ['version' => 4, 'providers' => [[
            'provider' => 'skyroom', 'applicable' => true, 'readiness' => 'ready',
        ]]],
        'provisioning_data' => ['custom' => 'preserved'],
    ]);
    $this->authorized_user([PermissionEnum::ENROLLMENT_RETRY_PROVISION->value]);

    $this->getJson(route('api.v1.admin.enrollments.provisioning-plan.preview', ['enrollment' => $enrollment->id]))
        ->assertOk()
        ->assertJsonPath('data.current_version', 4)
        ->assertJsonPath('data.next_version', 5)
        ->assertJsonPath('data.removed', ['skyroom']);

    $this->postJson(route('api.v1.admin.enrollments.provisioning-plan.apply', ['enrollment' => $enrollment->id]), [
        'confirm' => true,
    ])->assertOk();

    expect($enrollment->fresh()->provisioning_plan['version'])->toBe(5)
        ->and($enrollment->fresh()->provisioning_data['custom'])->toBe('preserved')
        ->and($enrollment->fresh()->provisioning_data['plan_history'][0]['staff_id'])->toBe($this->user->id);
});

it('does not apply a provisioning plan when confirmation is absent', function (): void {
    $enrollment = recoveryEnrollment();
    $plan       = $enrollment->provisioning_plan;
    $data       = $enrollment->provisioning_data;
    $this->authorized_user([PermissionEnum::ENROLLMENT_RETRY_PROVISION->value]);

    $this->postJson(route('api.v1.admin.enrollments.provisioning-plan.apply', ['enrollment' => $enrollment->id]), [
        'confirm' => false,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['confirm']);

    expect($enrollment->fresh()->provisioning_plan)->toEqual($plan)
        ->and($enrollment->fresh()->provisioning_data)->toEqual($data);
});

it('waives an eligible provider for a user with the dedicated permission', function (): void {
    $enrollment = recoveryEnrollment('moodle');
    $this->authorized_user([PermissionEnum::ENROLLMENT_WAIVE_PROVISION->value]);

    $this->postJson(route('api.v1.admin.enrollments.provisioning.waive', ['enrollment' => $enrollment->id]), [
        'provider' => 'moodle',
        'reason'   => 'Approved exception.',
    ])->assertOk();

    $attempt = ProvisioningAttempt::query()->where('enrollment_id', $enrollment->id)->firstOrFail();
    expect($attempt->status)->toBe(ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($attempt->failure_metadata['waived'])->toBeTrue()
        ->and($attempt->failure_message)->toBe('Approved exception.')
        ->and($attempt->staff_id)->toBe($this->user->id)
        ->and($enrollment->fresh()->provisioning_summary['status'])->toBe(App\Enums\ProvisioningStatusEnum::HEALTHY);
});

it('forbids a user without waiver permission and preserves provisioning state', function (): void {
    $enrollment = recoveryEnrollment('moodle');
    $before     = $enrollment->provisioning_data;
    $this->authorized_user([PermissionEnum::ENROLLMENT_RETRY_PROVISION->value]);

    $this->postJson(route('api.v1.admin.enrollments.provisioning.waive', ['enrollment' => $enrollment->id]), [
        'provider' => 'moodle',
        'reason'   => 'Approved exception.',
    ])->assertForbidden();

    expect($enrollment->fresh()->provisioning_data)->toEqual($before)
        ->and(ProvisioningAttempt::query()->where('enrollment_id', $enrollment->id)->count())->toBe(0);
});

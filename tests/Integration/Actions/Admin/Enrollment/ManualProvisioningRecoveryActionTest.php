<?php

declare(strict_types=1);

use App\Actions\Admin\Enrollment\ManualProvisioningRecoveryAction;
use App\Data\Admin\Enrollment\ManualProvisioningResolutionData;
use App\Data\Admin\Enrollment\ManualProvisioningWaiverData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Models\Enrollment;
use App\Models\ProvisioningAttempt;
use App\Models\Staff;
use Illuminate\Validation\ValidationException;

it('records a staff-attributed manual provider resolution', function (): void {
    $enrollment = Enrollment::factory()->create(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);
    $enrollment->update([
        'provisioning_plan' => ['version' => 1, 'providers' => [['provider' => 'skyroom', 'applicable' => true, 'readiness' => 'ready']], 'status' => 'manual_action_required'],
        'provisioning_data' => ['providers' => ['skyroom' => ['status' => 'manual_action_required']]],
    ]);
    $staff = Staff::factory()->create();

    $result = app(ManualProvisioningRecoveryAction::class)->resolve(
        $enrollment,
        new ManualProvisioningResolutionData(ProvisioningProviderEnum::SKYROOM, ['room_id' => 42], 'Room was verified by support.'),
        $staff->id,
    );

    expect($result->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE)
        ->and(ProvisioningAttempt::query()->where('staff_id', $staff->id)->where('provider', 'skyroom')->exists())->toBeTrue();
});

it('waives a provider and activates only after all requirements are resolved', function (): void {
    $enrollment = Enrollment::factory()->create(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);
    $enrollment->update([
        'provisioning_plan' => ['version' => 1, 'providers' => [['provider' => 'moodle', 'applicable' => true, 'readiness' => 'ready']], 'status' => 'manual_action_required'],
        'provisioning_data' => ['providers' => ['moodle' => ['status' => 'manual_action_required']]],
    ]);

    $result = app(ManualProvisioningRecoveryAction::class)->waive(
        $enrollment,
        new ManualProvisioningWaiverData(ProvisioningProviderEnum::MOODLE, 'Customer received access through an approved exception.'),
        Staff::factory()->create()->id,
    );

    expect($result->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE)
        ->and($result->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY);
});

it('rejects references for a provider outside the canonical plan', function (): void {
    $enrollment = Enrollment::factory()->create();
    $enrollment->update(['provisioning_plan' => ['version' => 1, 'providers' => [], 'status' => 'healthy']]);

    expect(fn () => app(ManualProvisioningRecoveryAction::class)->resolve(
        $enrollment,
        new ManualProvisioningResolutionData(ProvisioningProviderEnum::BBB, ['meeting_id' => 'room'], 'Verified.'),
        Staff::factory()->create()->id,
    ))->toThrow(ValidationException::class);
});

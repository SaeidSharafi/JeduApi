<?php

declare(strict_types=1);

use App\Actions\Admin\Enrollment\ManualProvisioningRecoveryAction;
use App\Data\Admin\Enrollment\ManualProvisioningResolutionData;
use App\Data\Admin\Enrollment\ManualProvisioningWaiverData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Models\Enrollment;
use App\Models\ProductDeliveryOption;
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
        new ManualProvisioningResolutionData(ProvisioningProviderEnum::SKYROOM, ['room_id' => 42], 'Verified.'),
        Staff::factory()->create()->id,
    ))->toThrow(ValidationException::class);
});

it('previews and archives a replaced provisioning plan with staff attribution', function (): void {
    $this->freezeTime();
    $option = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::DIRECT_DOWNLOAD,
        'details_json'    => [],
    ]);
    $old = ['version' => 4, 'providers' => [[
        'provider' => 'skyroom', 'applicable' => true, 'readiness' => 'ready',
    ]]];
    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $option->id,
        'provisioning_plan'          => $old,
        'provisioning_data'          => ['custom' => 'preserved'],
    ]);
    $enrollment->updateQuietly(['provisioning_plan' => $old]);
    $staff  = Staff::factory()->create();
    $action = app(ManualProvisioningRecoveryAction::class);

    $preview = $action->preview($enrollment);
    expect($preview->current_version)->toBe(4)
        ->and($preview->next_version)->toBe(5)
        ->and($preview->removed)->toBe(['skyroom'])
        ->and($preview->added)->toBe([])
        ->and($preview->changed)->toBe([]);

    $result = $action->apply($enrollment, true, $staff->id);

    expect($result->provisioning_plan['version'])->toBe(5)
        ->and($result->provisioning_plan['providers'])->toBe([])
        ->and($result->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($result->provisioning_data['custom'])->toBe('preserved')
        ->and($result->provisioning_data['plan_history'])->toEqual([[
            'version'    => 4,
            'plan'       => $old,
            'staff_id'   => $staff->id,
            'applied_at' => now()->toISOString(),
        ]]);
});

it('does not replace a provisioning plan without confirmation', function (): void {
    $enrollment = Enrollment::factory()->create();
    $oldPlan    = $enrollment->provisioning_plan;
    $oldData    = $enrollment->provisioning_data;

    try {
        app(ManualProvisioningRecoveryAction::class)->apply($enrollment, false, Staff::factory()->create()->id);
        $this->fail('An unconfirmed rebuild must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('confirm');
    }

    expect($enrollment->fresh()->provisioning_plan)->toEqual($oldPlan)
        ->and($enrollment->fresh()->provisioning_data)->toEqual($oldData);
});

it('rejects invalid provider references without recording a manual resolution', function (ProvisioningProviderEnum $provider, array $references): void {
    $plan = ['version' => 1, 'providers' => [[
        'provider' => $provider->value, 'applicable' => true, 'readiness' => 'ready',
    ]]];
    $enrollment = Enrollment::factory()->create(['provisioning_plan' => $plan]);
    $enrollment->updateQuietly(['provisioning_plan' => $plan]);

    try {
        app(ManualProvisioningRecoveryAction::class)->resolve(
            $enrollment,
            new ManualProvisioningResolutionData($provider, $references, 'Verified by staff'),
            Staff::factory()->create()->id,
        );
        $this->fail('Invalid references must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('references');
    }

    expect($enrollment->provisioningAttempts()->count())->toBe(0);
})->with([
    'zero room'              => [ProvisioningProviderEnum::SKYROOM, ['room_id' => 0]],
    'missing moodle course'  => [ProvisioningProviderEnum::MOODLE, ['moodle_user_id' => 1]],
    'negative quiz user'     => [ProvisioningProviderEnum::MOODLE_QUIZ, ['moodle_user_id' => -1, 'moodle_course_id' => 2]],
    'missing IMS enrollment' => [ProvisioningProviderEnum::IMS, ['ims_student_id' => 1]],
    'blank license'          => [ProvisioningProviderEnum::SPOTPLAYER, ['license_key' => ' ']],
]);

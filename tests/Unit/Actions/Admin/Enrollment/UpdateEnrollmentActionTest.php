<?php

declare(strict_types=1);

use App\Actions\Admin\Enrollment\UpdateEnrollmentAction;
use App\Data\Admin\Enrollment\EnrollmentUpdateData;
use App\Models\Enrollment;
use App\Services\Provisioning\ProvisioningAttemptService;

describe('UpdateEnrollmentAction', function (): void {
    beforeEach(function (): void {
        $this->action = new UpdateEnrollmentAction(app(ProvisioningAttemptService::class));
    });

    it('updates enrollment with all fields', function (): void {
        $enrollment = Enrollment::factory()->create([
            'access_start_date' => '2025-01-01',
            'access_end_date'   => '2025-12-31',
            'notes'             => 'Old notes',
        ]);

        $data = EnrollmentUpdateData::from([
            'access_start_date' => '1404-10-11',
            'access_end_date'   => '1405-10-10',
            'notes'             => 'New notes',
        ]);

        $result = $this->action->handle($enrollment, $data);

        expect($result->access_start_date->format('Y-m-d'))->toBe('2026-01-01')
            ->and($result->access_end_date->format('Y-m-d'))->toBe('2026-12-31')
            ->and($result->notes)->toBe('New notes');

        $this->assertDatabaseHas('enrollments', [
            'id'                => $enrollment->id,
            'access_start_date' => '2026-01-01',
            'access_end_date'   => '2026-12-31',
            'notes'             => 'New notes',
        ]);
    });

    it('updates enrollment with nullable fields', function (): void {
        $enrollment = Enrollment::factory()->create([
            'access_start_date' => '2025-01-01',
            'access_end_date'   => '2025-12-31',
            'notes'             => 'Some notes',
        ]);

        $data = EnrollmentUpdateData::from([
            'access_start_date' => null,
            'access_end_date'   => null,
            'notes'             => null,
        ]);

        $result = $this->action->handle($enrollment, $data);

        expect($result->access_start_date)->toBeNull()
            ->and($result->access_end_date)->toBeNull()
            ->and($result->notes)->toBeNull();

        $this->assertDatabaseHas('enrollments', [
            'id'                => $enrollment->id,
            'access_start_date' => null,
            'access_end_date'   => null,
            'notes'             => null,
        ]);
    });

    it('returns fresh enrollment instance', function (): void {
        $enrollment = Enrollment::factory()->create([
            'notes' => 'Original notes',
        ]);

        $data = EnrollmentUpdateData::from([
            'access_start_date' => null,
            'access_end_date'   => null,
            'notes'             => 'Updated notes',
        ]);

        $result = $this->action->handle($enrollment, $data);

        expect($result)->toBeInstanceOf(Enrollment::class)
            ->and($result->notes)->toBe('Updated notes')
            ->and($result->wasRecentlyCreated)->toBeFalse();
    });

    it('appends the reason to enrollment notes without changing access dates', function (): void {
        $enrollment = Enrollment::factory()->create([
            'access_start_date' => '2025-01-01',
            'access_end_date'   => '2025-12-31',
            'notes'             => 'Existing notes',
        ]);

        $result = $this->action->handle($enrollment, EnrollmentUpdateData::from([
            'access_start_date' => '1403-10-12',
            'access_end_date'   => '1404-10-10',
            'notes'             => 'Existing notes',
            'reason'            => 'Customer requested an extension.',
        ]));

        expect($result->notes)->toContain('Existing notes', 'Customer requested an extension.')
            ->and($result->access_start_date->format('Y-m-d'))->toBe('2025-01-01')
            ->and($result->access_end_date->format('Y-m-d'))->toBe('2025-12-31');
    });
});

<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Enrollment;
use App\Models\User;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

describe('EnrollmentController', function (): void {
    it('can list enrollments with permissions', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_VIEW_ANY->value]);

        Enrollment::factory()->count(3)->create();

        $response = $this->getJson(route('api.v1.admin.enrollment.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'enrollment_status', 'customer', 'order'],
                    ],
                ],
            ]);
    });

    it('can filter enrollments by status', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_VIEW_ANY->value]);

        Enrollment::factory()->create(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);
        Enrollment::factory()->create(['enrollment_status' => EnrollmentStatusEnum::CANCELLED]);

        $response = $this->getJson(route('api.v1.admin.enrollment.index', [
            'filter[enrollment_status]' => EnrollmentStatusEnum::ACTIVE->value,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.enrollment_status.value', EnrollmentStatusEnum::ACTIVE->value);
    });

    it('can filter enrollments by customer_id', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_VIEW_ANY->value]);

        $customer1 = User::factory()->create();
        $customer2 = User::factory()->create();

        Enrollment::factory()->create(['customer_id' => $customer1->id]);
        Enrollment::factory()->create(['customer_id' => $customer2->id]);

        $response = $this->getJson(route('api.v1.admin.enrollment.index', [
            'filter[customer_id]' => $customer1->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.customer.id', $customer1->id);
    });

    it('can filter enrollments by order_id', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_VIEW_ANY->value]);

        $enrollment1 = Enrollment::factory()->create();
        $enrollment2 = Enrollment::factory()->create();

        $response = $this->getJson(route('api.v1.admin.enrollment.index', [
            'filter[order_id]' => $enrollment1->order_id,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.order.id', $enrollment1->order_id);
    });

    it('can sort enrollments by created_at ascending', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_VIEW_ANY->value]);

        $enrollment1 = Enrollment::factory()->create(['created_at' => now()->subDays(2)]);
        $enrollment2 = Enrollment::factory()->create(['created_at' => now()->subDay()]);

        $response = $this->getJson(route('api.v1.admin.enrollment.index', ['sort' => 'created_at']));

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', $enrollment1->id)
            ->assertJsonPath('data.data.1.id', $enrollment2->id);
    });

    it('can sort enrollments by created_at descending by default', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_VIEW_ANY->value]);

        $enrollment1 = Enrollment::factory()->create(['created_at' => now()->subDays(2)]);
        $enrollment2 = Enrollment::factory()->create(['created_at' => now()->subDay()]);

        $response = $this->getJson(route('api.v1.admin.enrollment.index'));

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', $enrollment2->id)
            ->assertJsonPath('data.data.1.id', $enrollment1->id);
    });

    it('can show single enrollment with permissions', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_VIEW->value]);

        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
            'notes'             => 'Test enrollment notes',
        ]);

        $response = $this->getJson(route('api.v1.admin.enrollment.show', ['enrollment' => $enrollment->id]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'uuid', 'enrollment_status', 'customer', 'order',
                    'productDeliveryOption', 'notes', 'created_at',
                    ],
            ])
            ->assertJsonPath('data.uuid', $enrollment->uuid)
            ->assertJsonPath('data.enrollment_status.value', EnrollmentStatusEnum::ACTIVE->value);
    });

    it('can update enrollment with permissions', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_UPDATE->value]);

        $enrollment = Enrollment::factory()->create([
            'access_start_date'      => '2025-01-01',
            'access_end_date'        => '2025-12-31',
            'external_enrollment_id' => 11111,
            'notes'                  => 'Old notes',
        ]);

        $updateData = [
            'access_start_date'      => '2026-01-01',
            'access_end_date'        => '2026-12-31',
            'external_enrollment_id' => 22222,
            'notes'                  => 'Updated notes',
        ];

        $response = $this->putJson(route('api.v1.admin.enrollment.update', ['enrollment' => $enrollment->id]), $updateData);

        $response->assertOk()
            ->assertJsonPath('data.access_start_date', '1404-10-11')
            ->assertJsonPath('data.access_end_date', '1405-10-10')
            ->assertJsonPath('data.external_enrollment_id', 22222)
            ->assertJsonPath('data.notes', 'Updated notes');

        $this->assertDatabaseHas('enrollments', [
            'id'                     => $enrollment->id,
            'access_start_date'      => '2026-01-01',
            'access_end_date'        => '2026-12-31',
            'external_enrollment_id' => 22222,
            'notes'                  => 'Updated notes',
        ]);
    });

    it('can delete cancelled enrollment with permissions', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_DELETE->value]);

        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::CANCELLED,
        ]);

        $enrollmentId = $enrollment->id;

        $response = $this->deleteJson(route('api.v1.admin.enrollment.destroy', ['enrollment' => $enrollment->id]));

        $response->assertNoContent();

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollmentId]);
    });

    it('cannot delete active enrollment', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_DELETE->value]);

        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
        ]);

        $response = $this->deleteJson(route('api.v1.admin.enrollment.destroy', ['enrollment' => $enrollment->id]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['enrollment_status']);

        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
    });

    it('cannot access index without permissions', function (): void {
        $this->unauthorized_user();

        $response = $this->getJson(route('api.v1.admin.enrollment.index'));

        $response->assertForbidden();
    });

    it('cannot access show without permissions', function (): void {
        $this->unauthorized_user();

        $enrollment = Enrollment::factory()->create();

        $response = $this->getJson(route('api.v1.admin.enrollment.show', ['enrollment' => $enrollment->id]));

        $response->assertForbidden();
    });

    it('cannot update without permissions', function (): void {
        $this->unauthorized_user();

        $enrollment = Enrollment::factory()->create();

        $response = $this->putJson(route('api.v1.admin.enrollment.update', ['enrollment' => $enrollment->id]), [
            'notes' => 'Test',
        ]);

        $response->assertForbidden();
    });

    it('cannot delete without permissions', function (): void {
        $this->unauthorized_user();

        $enrollment = Enrollment::factory()->create();

        $response = $this->deleteJson(route('api.v1.admin.enrollment.destroy', ['enrollment' => $enrollment->id]));

        $response->assertForbidden();
    });
});

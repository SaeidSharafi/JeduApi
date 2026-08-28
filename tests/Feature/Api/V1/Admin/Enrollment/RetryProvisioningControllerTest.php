<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Jobs\Provisioning\ProvisionImsEnrollmentJob;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

describe('RetryProvisioningController', function (): void {
    beforeEach(function (): void {
        Queue::fake();
    });

    it('can retry provisioning with failed providers', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_RETRY_PROVISION->value]);

        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
        ]);

        $enrollment = Enrollment::factory()->create([
            'product_delivery_option_id' => $pdo->id,
            'enrollment_status'          => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data'          => [
                'providers' => [
                    'moodle' => ['status' => 'failed', 'error' => 'Connection timeout'],
                ],
            ],
        ]);

        $response = $this->postJson(route('api.v1.admin.enrollments.retry-provisioning', ['enrollment' => $enrollment->id]));

        $response->assertOk()
            ->assertJsonPath('data.message', 'Retry dispatched for 1 provider(s)')
            ->assertJsonPath('data.providers', ['moodle']);

        Queue::assertPushed(ProvisionEnrollmentProviderJob::class);
    });

    it('returns 422 for enrollment not in failed status', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_RETRY_PROVISION->value]);

        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
        ]);

        $response = $this->postJson(route('api.v1.admin.enrollments.retry-provisioning', ['enrollment' => $enrollment->id]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['enrollment_status']);
    });

    it('returns 422 when no failed providers found', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_RETRY_PROVISION->value]);

        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data' => [
                'providers' => [
                    'moodle' => ['status' => 'success'],
                ],
            ],
        ]);

        $response = $this->postJson(route('api.v1.admin.enrollments.retry-provisioning', ['enrollment' => $enrollment->id]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provisioning_data']);
    });

    it('can retry multiple failed providers', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_RETRY_PROVISION->value]);

        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
            'details_json'    => ['ims_course_code' => 'IMS_COURSE_123'],
        ]);

        $enrollment = Enrollment::factory()->create([
            'product_delivery_option_id' => $pdo->id,
            'enrollment_status'          => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data'          => [
                'providers' => [
                    'ims'    => ['status' => 'failed'],
                    'moodle' => ['status' => 'failed'],
                ],
            ],
        ]);

        Payment::factory()->create([
            'order_id' => $enrollment->order_id,
            'status'   => PaymentStatusEnum::COMPLETED,
        ]);

        $response = $this->postJson(route('api.v1.admin.enrollments.retry-provisioning', ['enrollment' => $enrollment->id]));

        $response->assertOk()
            ->assertJsonPath('data.message', 'Retry dispatched for 2 provider(s)')
            ->assertJsonPath('data.providers', ['ims', 'moodle']);

        Queue::assertPushed(ProvisionImsEnrollmentJob::class);
        Queue::assertPushed(ProvisionEnrollmentProviderJob::class);
    });

    it('works with pending provisioning status', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_RETRY_PROVISION->value]);

        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
        ]);

        $enrollment = Enrollment::factory()->create([
            'product_delivery_option_id' => $pdo->id,
            'enrollment_status'          => EnrollmentStatusEnum::PENDING_PROVISIONING,
            'provisioning_data'          => [
                'providers' => [
                    'moodle' => ['status' => 'failed'],
                ],
            ],
        ]);

        $response = $this->postJson(route('api.v1.admin.enrollments.retry-provisioning', ['enrollment' => $enrollment->id]));

        $response->assertOk();

        Queue::assertPushed(ProvisionEnrollmentProviderJob::class);
    });

    it('cannot retry provisioning without permissions', function (): void {
        $this->unauthorized_user();

        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data' => [
                'providers' => [
                    'moodle' => ['status' => 'failed'],
                ],
            ],
        ]);

        $response = $this->postJson(route('api.v1.admin.enrollments.retry-provisioning', ['enrollment' => $enrollment->id]));

        $response->assertForbidden();
    });
});

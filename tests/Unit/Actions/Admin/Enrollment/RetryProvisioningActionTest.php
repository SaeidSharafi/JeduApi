<?php

declare(strict_types=1);

use App\Actions\Admin\Enrollment\RetryProvisioningAction;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Jobs\Provisioning\ProvisionImsEnrollmentJob;
use App\Jobs\Provisioning\ProvisionMoodleQuizJob;
use App\Jobs\Provisioning\ProvisionSkyroomEnrollmentJob;
use App\Jobs\Provisioning\ProvisionSpotPlayerEnrollmentJob;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Services\Enrollment\ProvisioningPlanResolver;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

describe('RetryProvisioningAction', function (): void {
    beforeEach(function (): void {
        $this->action = new RetryProvisioningAction(
            app(ProvisioningPlanResolver::class),
            app(App\Services\Provisioning\ProvisioningAttemptService::class),
        );
        Queue::fake();
    });

    it('throws exception for enrollment not in failed or pending provisioning status', function (): void {
        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
        ]);

        expect(fn () => $this->action->handle($enrollment))
            ->toThrow(ValidationException::class);
    });

    it('throws exception when no failed providers found', function (): void {
        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data' => [
                'providers' => [
                    'ims'    => ['status' => 'success'],
                    'moodle' => ['status' => 'success'],
                ],
            ],
        ]);

        expect(fn () => $this->action->handle($enrollment))
            ->toThrow(ValidationException::class, 'No failed providers found to retry');
    });

    it('dispatches all required providers when provisioning_data is null', function (): void {
        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
            'details_json'    => ['ims_course_code' => 'TEST-123'],
        ]);

        $enrollment = Enrollment::factory()->create([
            'enrollment_status'          => EnrollmentStatusEnum::PENDING_PROVISIONING,
            'product_delivery_option_id' => $pdo->id,
            'provisioning_data'          => null, // Never provisioned
        ]);

        Payment::factory()->create([
            'order_id' => $enrollment->order_id,
            'status'   => PaymentStatusEnum::COMPLETED,
        ]);

        $result = $this->action->handle($enrollment);

        expect($result['message'])->toContain('Initial provisioning dispatched');
        expect($result['providers'])->toContain('ims', 'moodle');

        Queue::assertPushed(ProvisionImsEnrollmentJob::class);
        Queue::assertPushed(ProvisionEnrollmentProviderJob::class);
    });

    it('dispatches IMS provisioning job for failed IMS provider', function (): void {
        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
            'details_json'    => ['ims_course_code' => 'IMS_COURSE_123'],
        ]);

        $enrollment = Enrollment::factory()->create([
            'product_delivery_option_id' => $pdo->id,
            'enrollment_status'          => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data'          => [
                'providers' => [
                    'ims' => ['status' => 'failed', 'error' => 'Connection timeout'],
                ],
            ],
        ]);

        Payment::factory()->create([
            'order_id' => $enrollment->order_id,
            'status'   => PaymentStatusEnum::COMPLETED,
        ]);

        $result = $this->action->handle($enrollment);

        expect($result['message'])->toBe('Retry dispatched for 1 provider(s)')
            ->and($result['providers'])->toBe(['ims']);

        Queue::assertPushed(ProvisionImsEnrollmentJob::class, function ($job) use ($enrollment): bool {
            return $job->enrollmentId === $enrollment->id;
        });
    });

    it('dispatches Moodle provisioning job for failed Moodle provider', function (): void {
        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
        ]);

        $enrollment = Enrollment::factory()->create([
            'product_delivery_option_id' => $pdo->id,
            'enrollment_status'          => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data'          => [
                'providers' => [
                    'moodle' => ['status' => 'failed'],
                ],
            ],
        ]);

        $result = $this->action->handle($enrollment);

        expect($result['providers'])->toBe(['moodle']);

        Queue::assertPushed(ProvisionEnrollmentProviderJob::class);
    });

    it('dispatches SpotPlayer provisioning job for failed SpotPlayer provider', function (): void {
        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
        ]);

        $enrollment = Enrollment::factory()->create([
            'product_delivery_option_id' => $pdo->id,
            'enrollment_status'          => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data'          => [
                'providers' => [
                    'spotplayer' => ['status' => 'failed'],
                ],
            ],
        ]);

        $result = $this->action->handle($enrollment);

        expect($result['providers'])->toBe(['spotplayer']);

        Queue::assertPushed(ProvisionSpotPlayerEnrollmentJob::class);
    });

    it('dispatches BBB provisioning job for failed BBB provider', function (): void {
        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LIVE_SESSION_BBB,
        ]);

        $enrollment = Enrollment::factory()->create([
            'product_delivery_option_id' => $pdo->id,
            'enrollment_status'          => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data'          => [
                'providers' => [
                    'bbb' => ['status' => 'failed'],
                ],
            ],
        ]);

        $result = $this->action->handle($enrollment);

        expect($result['providers'])->toBe(['bbb']);

        Queue::assertPushed(ProvisionBbbEnrollmentJob::class);
    });

    it('dispatches Skyroom provisioning job for failed Skyroom provider', function (): void {
        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LIVE_SESSION_SKYROOM,
        ]);

        $enrollment = Enrollment::factory()->create([
            'product_delivery_option_id' => $pdo->id,
            'enrollment_status'          => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data'          => [
                'providers' => [
                    'skyroom' => ['status' => 'failed'],
                ],
            ],
        ]);

        $result = $this->action->handle($enrollment);

        expect($result['providers'])->toBe(['skyroom']);

        Queue::assertPushed(ProvisionSkyroomEnrollmentJob::class);
    });

    it('dispatches Moodle Quiz provisioning job for failed Moodle Quiz provider', function (): void {
        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
            'details_json'    => ['moodle_quiz_course_id' => 456],
        ]);

        $enrollment = Enrollment::factory()->create([
            'product_delivery_option_id' => $pdo->id,
            'enrollment_status'          => EnrollmentStatusEnum::PROVISIONING_FAILED,
            'provisioning_data'          => [
                'providers' => [
                    'moodle_quiz' => ['status' => 'failed'],
                ],
            ],
        ]);

        $result = $this->action->handle($enrollment);

        expect($result['providers'])->toBe(['moodle_quiz']);

        Queue::assertPushed(ProvisionMoodleQuizJob::class);
    });

    it('dispatches multiple provisioning jobs for multiple failed providers', function (): void {
        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
            'details_json'    => ['ims_course_code' => 'IMS_123'],
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

        $result = $this->action->handle($enrollment);

        expect($result['message'])->toBe('Retry dispatched for 2 provider(s)')
            ->and($result['providers'])->toBe(['ims', 'moodle']);

        Queue::assertPushed(ProvisionImsEnrollmentJob::class);
        Queue::assertPushed(ProvisionEnrollmentProviderJob::class);
    });

    it('works with pending provisioning status', function (): void {
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

        $result = $this->action->handle($enrollment);

        expect($result['providers'])->toBe(['moodle']);
        Queue::assertPushed(ProvisionEnrollmentProviderJob::class);
    });
});

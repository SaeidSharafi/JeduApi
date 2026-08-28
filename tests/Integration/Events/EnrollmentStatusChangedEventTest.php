<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Events\EnrollmentStatusChanged;
use App\Models\Enrollment;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\Event;

it('dispatches event when enrollment is created', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->create(['capacity' => 10, 'enrolled_count' => 0]);
    Event::fake([EnrollmentStatusChanged::class]);
    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $deliveryOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    Event::assertDispatched(EnrollmentStatusChanged::class, function ($event) use ($enrollment): bool {
        return $event->enrollment->id === $enrollment->id;
    });
});

it('dispatches event when enrollment status is updated', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->create(['capacity' => 10, 'enrolled_count' => 1]);

    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $deliveryOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    Event::fake([EnrollmentStatusChanged::class]);

    $enrollment->enrollment_status = EnrollmentStatusEnum::CANCELLED;
    $enrollment->save();

    Event::assertDispatched(EnrollmentStatusChanged::class, function ($event) use ($enrollment): bool {
        return $event->enrollment->id === $enrollment->id;
    });
});
it('dispatches event when enrollment is deleted', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->create(['capacity' => 10, 'enrolled_count' => 1]);

    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $deliveryOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    Event::fake([EnrollmentStatusChanged::class]);

    $enrollment->delete();

    Event::assertDispatched(EnrollmentStatusChanged::class, function ($event) use ($enrollment): bool {
        return $event->enrollment->id === $enrollment->id;
    });
});
it('increments enrolled_count when enrollment is created with ACTIVE status', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->make(['capacity' => 10, 'enrolled_count' => 0]);
    $deliveryOption->save(); // This will trigger boot and generate UUID

    Enrollment::factory()->create([
        'product_delivery_option_id' => $deliveryOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    expect($deliveryOption->fresh()->enrolled_count)->toBe(1);
});

it('does not increment enrolled_count when enrollment is created with CANCELLED status', function (): void {

    $deliveryOption = ProductDeliveryOption::factory()->create(['capacity' => 10, 'enrolled_count' => 0]);

    Enrollment::factory()->create([
        'product_delivery_option_id' => $deliveryOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::CANCELLED,
    ]);

    expect($deliveryOption->fresh()->enrolled_count)->toBe(0);
});

it('decrements enrolled_count when status changes from ACTIVE to CANCELLED', function (): void {

    $deliveryOption = ProductDeliveryOption::factory()->create(['capacity' => 10, 'enrolled_count' => 0]);

    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $deliveryOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    expect($deliveryOption->fresh()->enrolled_count)->toBe(1);

    $enrollment->enrollment_status = EnrollmentStatusEnum::CANCELLED;
    $enrollment->save();

    expect($deliveryOption->fresh()->enrolled_count)->toBe(0);
});

it('decrements enrolled_count when status changes from ACTIVE to EXPIRED', function (): void {

    $deliveryOption = ProductDeliveryOption::factory()->create(['capacity' => 10, 'enrolled_count' => 0]);

    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $deliveryOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    expect($deliveryOption->fresh()->enrolled_count)->toBe(1);

    $enrollment->enrollment_status = EnrollmentStatusEnum::EXPIRED;
    $enrollment->save();

    expect($deliveryOption->fresh()->enrolled_count)->toBe(0);
});

it('does not change enrolled_count when status changes from ACTIVE to SUSPENDED', function (): void {

    $deliveryOption = ProductDeliveryOption::factory()->create(['capacity' => 10, 'enrolled_count' => 0]);

    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $deliveryOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    expect($deliveryOption->fresh()->enrolled_count)->toBe(1);

    $enrollment->enrollment_status = EnrollmentStatusEnum::SUSPENDED;
    $enrollment->save();

    // SUSPENDED still occupies a seat, so count should remain 1
    expect($deliveryOption->fresh()->enrolled_count)->toBe(1);
});

it('increments enrolled_count when status changes from CANCELLED to ACTIVE', function (): void {

    $deliveryOption = ProductDeliveryOption::factory()->create(['capacity' => 10, 'enrolled_count' => 0]);

    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $deliveryOption->id,
        'enrollment_status'          => EnrollmentStatusEnum::CANCELLED,
    ]);
    $enrollment->updateQuietly([
        'provisioning_plan' => ['version' => 1, 'providers' => [], 'status' => 'healthy'],
    ]);

    expect($deliveryOption->fresh()->enrolled_count)->toBe(0);

    $enrollment->enrollment_status = EnrollmentStatusEnum::ACTIVE;
    $enrollment->save();

    expect($deliveryOption->fresh()->enrolled_count)->toBe(1);
});

it('handles multiple enrollments correctly', function (): void {

    $deliveryOption = ProductDeliveryOption::factory()->create(['capacity' => 10, 'enrolled_count' => 0]);

    // Create 3 active enrollments
    for ($i = 0; $i < 3; $i++) {
        Enrollment::factory()->create([
            'product_delivery_option_id' => $deliveryOption->id,
            'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        ]);
    }

    expect($deliveryOption->fresh()->enrolled_count)->toBe(3);

    // Create 2 cancelled enrollments (should not affect count)
    for ($i = 0; $i < 2; $i++) {
        Enrollment::factory()->create([
            'product_delivery_option_id' => $deliveryOption->id,
            'enrollment_status'          => EnrollmentStatusEnum::CANCELLED,
        ]);
    }

    expect($deliveryOption->fresh()->enrolled_count)->toBe(3);
});

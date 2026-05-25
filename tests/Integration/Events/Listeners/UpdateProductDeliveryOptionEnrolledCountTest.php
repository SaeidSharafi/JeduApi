<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Events\EnrollmentStatusChanged;
use App\Listeners\UpdateProductDeliveryOptionEnrolledCount;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Event;

it('increment enrolled_count when status changes to ACTIVE', function () {
    Event::fake([
        EnrollmentStatusChanged::class,
    ]);
    $enrolment = Enrollment::factory()->create(
        ['enrollment_status' => EnrollmentStatusEnum::ACTIVE]
    );
    Event::assertDispatched(EnrollmentStatusChanged::class);
    $event = new EnrollmentStatusChanged($enrolment);
    expect($enrolment->productDeliveryOption->enrolled_count)->toBe(0);
    $listener = new UpdateProductDeliveryOptionEnrolledCount();
    $listener->handle($event);
    $enrolment->productDeliveryOption->refresh();
    expect($enrolment->productDeliveryOption->enrolled_count)->toBe(1);
});

it('increments when transitioning from non-occupying to occupying (AWAITING_PAYMENT -> PENDING_PROVISIONING)', function () {
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT,
    ]);

    $pdo = $enrollment->productDeliveryOption;
    expect($pdo->enrolled_count)->toBe(0);

    // Persist the state change as it would be in real flow
    $enrollment->enrollment_status = EnrollmentStatusEnum::PENDING_PROVISIONING;
    $enrollment->saveQuietly();

    $event    = new EnrollmentStatusChanged($enrollment);
    $listener = new UpdateProductDeliveryOptionEnrolledCount();
    $listener->handle($event);

    $pdo->refresh();
    expect($pdo->enrolled_count)->toBe(1);
});

it('decrements when transitioning from occupying to non-occupying (ACTIVE -> CANCELLED)', function () {
    Event::fake([EnrollmentStatusChanged::class]);
    // Start with 1 occupying seat recorded
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
    ]);
    $pdo = $enrollment->productDeliveryOption;

    // Ensure current DB reflects ACTIVE then project
    $enrollment->enrollment_status = EnrollmentStatusEnum::ACTIVE;
    $enrollment->saveQuietly();
    (new UpdateProductDeliveryOptionEnrolledCount())->handle(
        new EnrollmentStatusChanged($enrollment)
    );
    $pdo->refresh();
    expect($pdo->enrolled_count)->toBe(1);

    // Now move to non-occupying -> should decrement to 0
    $enrollment->enrollment_status = EnrollmentStatusEnum::CANCELLED;
    $enrollment->saveQuietly();
    (new UpdateProductDeliveryOptionEnrolledCount())->handle(
        new EnrollmentStatusChanged($enrollment)
    );
    $pdo->refresh();
    expect($pdo->enrolled_count)->toBe(0);
});

it('does nothing when transitioning within occupying (PENDING_PROVISIONING -> ACTIVE)', function () {
    Event::fake([EnrollmentStatusChanged::class]);
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT,
    ]);
    $pdo = $enrollment->productDeliveryOption;

    // Move into occupying first to bump from 0 -> 1
    $enrollment->enrollment_status = EnrollmentStatusEnum::PENDING_PROVISIONING;
    $enrollment->saveQuietly();
    (new UpdateProductDeliveryOptionEnrolledCount())->handle(
        new EnrollmentStatusChanged($enrollment)
    );
    $pdo->refresh();
    expect($pdo->enrolled_count)->toBe(1);

    // Now a within-group occupying change should not change the count
    $enrollment->enrollment_status = EnrollmentStatusEnum::ACTIVE;
    $enrollment->saveQuietly();
    (new UpdateProductDeliveryOptionEnrolledCount())->handle(
        new EnrollmentStatusChanged($enrollment)
    );
    $pdo->refresh();
    expect($pdo->enrolled_count)->toBe(1);
});

it('does nothing when transitioning within non-occupying (AWAITING_PAYMENT -> PROVISIONING_FAILED)', function () {
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT,
    ]);
    $pdo = $enrollment->productDeliveryOption;
    expect($pdo->enrolled_count)->toBe(0);

    $enrollment->enrollment_status = EnrollmentStatusEnum::PROVISIONING_FAILED;
    $enrollment->saveQuietly();
    (new UpdateProductDeliveryOptionEnrolledCount())->handle(
        new EnrollmentStatusChanged($enrollment)
    );
    $pdo->refresh();
    expect($pdo->enrolled_count)->toBe(0);
});

it('increments on creation when new status is occupying (oldStatus = null, new = PENDING_PROVISIONING)', function () {
    Event::fake([EnrollmentStatusChanged::class]);
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING,
    ]);
    $pdo = $enrollment->productDeliveryOption;
    expect($pdo->enrolled_count)->toBe(0);

    $eventCreate = new EnrollmentStatusChanged($enrollment);
    (new UpdateProductDeliveryOptionEnrolledCount())->handle($eventCreate);
    $pdo->refresh();
    expect($pdo->enrolled_count)->toBe(1);
});

it('does not increment on creation when new status is non-occupying (oldStatus = null, new = AWAITING_PAYMENT)', function () {
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT,
    ]);
    $pdo = $enrollment->productDeliveryOption;
    expect($pdo->enrolled_count)->toBe(0);

    $eventCreate = new EnrollmentStatusChanged($enrollment);
    (new UpdateProductDeliveryOptionEnrolledCount())->handle($eventCreate);
    $pdo->refresh();
    expect($pdo->enrolled_count)->toBe(0);
});
it('handle missing productDeliveryOption gracefully', function () {
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
    ]);

    $enrollment->productDeliveryOption->delete();

    $event    = new EnrollmentStatusChanged($enrollment);
    $listener = new UpdateProductDeliveryOptionEnrolledCount();

    $listener->handle($event);

    expect(true)->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Jobs\Provisioning\SyncMoodleProgressJob;
use App\Models\Course;
use App\Models\Enrollment;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer();
});

// ─── Ownership ───────────────────────────────────────────────────────────────

it('returns 404 for non-owner enrollment on show', function (): void {
    $other      = App\Models\User::factory()->create();
    $enrollment = createEnrollment($other, DeliveryMethodEnum::LMS_MOODLE);

    $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertNotFound()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);
});

it('returns enrollment detail for owner', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);

    $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.uuid', $enrollment->uuid);
});

// ─── Delivery blocks ─────────────────────────────────────────────────────────

it('show returns bbb delivery block for live_session_bbb enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_BBB);

    $response = $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    $block = $response->json('data.delivery_block');
    expect($block)->toHaveKey('join_url')
        ->and($block)->toHaveKey('past_recordings');
});

it('show returns moodle delivery block for lms_moodle enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, provisioning: true);

    $response = $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    $block = $response->json('data.delivery_block');
    expect($block)
        ->toHaveKey('course_url')
        ->and($block)->toHaveKey('visible')
        ->and($block)->toHaveKey('name')
        ->and($block)->toHaveKey('completed')
        ->and($block)->toHaveKey('course_grade')
        ->and($block)->toHaveKey('activities')
        ->and($block['activities'])->toBeArray()
    ;
});

it('show returns spotplayer delivery block for video_platform_spotplayer enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER);

    $response = $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    $block = $response->json('data.delivery_block');
    expect($block)->toHaveKey('license_key')
        ->and($block)->toHaveKey('player_url');
});

it('show returns in_person delivery block for in_person enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON);

    $response = $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    $block = $response->json('data.delivery_block');
    expect($block)->toHaveKey('address')
        ->and($block)->toHaveKey('map_url');
});

it('show returns direct_download delivery block for direct_download enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD);

    $response = $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    $block = $response->json('data.delivery_block');
    expect($block)->toHaveKey('files');
});

// ─── certificate_info ────────────────────────────────────────────────────────

it('certificate_info is null when productable does not provide certificate', function (): void {
    // DigitalAsset has no provides_certificate; Course factory has it as random bool
    // Force a Course with provides_certificate = false
    $course     = Course::factory()->create(['provides_certificate' => false]);
    $enrollment = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LMS_MOODLE, $course);

    $response = $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    expect($response->json('data.certificate_info.is_available'))->toBeFalse()
        ->and($response->json('data.certificate_info.certificate_url'))->toBeNull();
});

it('certificate_info is_available false when survey_completed_at is null', function (): void {
    $course                          = Course::factory()->create(['provides_certificate' => true]);
    $enrollment                      = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LMS_MOODLE, $course);
    $enrollment->survey_completed_at = null;
    $enrollment->save();

    $response = $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    expect($response->json('data.certificate_info.is_available'))->toBeFalse();
});

it('certificate_info is_available true when survey_completed_at is set', function (): void {
    $course                          = Course::factory()->create(['provides_certificate' => true]);
    $enrollment                      = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LMS_MOODLE, $course);
    $enrollment->survey_completed_at = now();
    $enrollment->save();

    $response = $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    expect($response->json('data.certificate_info.is_available'))->toBeTrue();
});

// ─── SWR dispatch ────────────────────────────────────────────────────────────

it('show dispatches SyncMoodleProgressJob (rate-limited) for provisioned moodle enrollment', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $moodleCourseId = 999;
    $moodleUserId   = 888;

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE->value,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'details_json'     => ['moodle_course_id' => $moodleCourseId],
    ]);

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'provisioning_data' => [
            'providers' => [
                'moodle' => [
                    'data' => [
                        'moodle_user_id' => $moodleUserId,
                        'course_info'    => [
                            'visible'    => true,
                            'name'       => 'Test Course',
                            'course_url' => null,
                            'completed'  => false,
                        ],
                    ],
                ],
            ],
        ],
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk();

    Illuminate\Support\Facades\Queue::assertPushed(SyncMoodleProgressJob::class);
});

it('show does not dispatch SWR job for non-moodle enrollment', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON);

    $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk();

    Illuminate\Support\Facades\Queue::assertNotPushed(SyncMoodleProgressJob::class);
});

it('show does not dispatch SWR job for moodle enrollment without provisioning data', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE->value,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'details_json'     => ['moodle_course_id' => 777],
    ]);

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    // Has course_info so show doesn't 500, but moodle_user_id is absent so SWR is skipped
    $enrollment->forceFill([
        'provisioning_data' => [
            'providers' => [
                'moodle' => [
                    'data' => [
                        'course_info' => [
                            'visible'    => true,
                            'name'       => 'Test Course',
                            'course_url' => null,
                            'completed'  => false,
                        ],
                    ],
                ],
            ],
        ],
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk();

    Illuminate\Support\Facades\Queue::assertNotPushed(SyncMoodleProgressJob::class);
});

it('show does not dispatch SyncMoodleProgressJob twice within decay window', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $moodleCourseId = 999;
    $moodleUserId   = 888;

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE->value,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'details_json'     => ['moodle_course_id' => $moodleCourseId],
    ]);

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'provisioning_data' => [
            'providers' => [
                'moodle' => [
                    'data' => [
                        'moodle_user_id' => $moodleUserId,
                        'course_info'    => [
                            'visible'    => true,
                            'name'       => 'Test Course',
                            'course_url' => null,
                            'completed'  => false,
                        ],
                    ],
                ],
            ],
        ],
    ])->saveQuietly();

    $url = route('api.v1.shop.my-courses.show', ['enrollment' => $enrollment->uuid]);

    $this->getJson($url)->assertOk();
    $this->getJson($url)->assertOk();

    Illuminate\Support\Facades\Queue::assertPushed(SyncMoodleProgressJob::class, 1);
});

// ─── Removed route ───────────────────────────────────────────────────────────

it('moodle update patch route returns 404', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);

    $this->patchJson("/api/v1/shop/my-courses/{$enrollment->uuid}/moodle/update")
        ->assertNotFound();
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Create enrollment with a specific productable already attached to the product.
 */
function createEnrollmentForProductable(
    App\Models\User $customer,
    DeliveryMethodEnum $deliveryMethod,
    mixed $productable,
): Enrollment {
    $product = App\Models\Product::factory()->create([
        'productable_type' => $productable::class,
        'productable_id'   => $productable->id,
    ]);

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => $deliveryMethod->value,
        'fulfillment_type' => $deliveryMethod->getFulfillmentType(),
        'product_id'       => $product->id,
    ]);

    return createEnrollment($customer, $deliveryMethod, deliveryOption: $deliveryOption);
}

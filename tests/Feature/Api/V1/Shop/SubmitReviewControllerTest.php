<?php

declare(strict_types=1);

use App\Actions\Shop\Student\SubmitReviewAction;
use App\Enums\Content\ReviewStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Api\Shop\Student\SubmitReviewController;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Review;
use App\Models\Seminar;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(SubmitReviewAction::class, SubmitReviewController::class);

beforeEach(function (): void {
    $this->customer();
});

it('creates a pending review for the enrollment productable', function (): void {
    $course     = Course::factory()->create();
    $enrollment = createEnrollmentForReview($this->user, productable: $course);

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [
        'rating'  => 5,
        'title'   => 'Excellent course',
        'comment' => 'Well structured and thorough.',
    ])
        ->assertCreated()
        ->assertJsonPath('message', __('messages.review.submitted'))
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.title', 'Excellent course')
        ->assertJsonPath('data.comment', 'Well structured and thorough.')
        ->assertJsonPath('data.status.value', ReviewStatusEnum::PENDING->value)
        ->assertJsonStructure(['data' => ['id', 'rating', 'title', 'comment', 'status' => ['value', 'label'], 'created_at']]);

    $this->assertDatabaseHas('reviews', [
        'user_id'         => $this->user->id,
        'reviewable_type' => $course->getMorphClass(),
        'reviewable_id'   => $course->id,
        'rating'          => 5,
        'title'           => 'Excellent course',
        'comment'         => 'Well structured and thorough.',
        'status'          => ReviewStatusEnum::PENDING->value,
        'is_featured'     => false,
    ]);
});

it('creates a review for a seminar enrollment', function (): void {
    $seminar    = Seminar::factory()->create();
    $enrollment = createEnrollmentForReview($this->user, productable: $seminar);

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [
        'rating'  => 4,
        'title'   => 'Good seminar',
        'comment' => 'Very useful.',
    ])->assertCreated();

    $this->assertDatabaseHas('reviews', [
        'user_id'         => $this->user->id,
        'reviewable_type' => $seminar->getMorphClass(),
        'reviewable_id'   => $seminar->id,
    ]);
});

it('creates a review for a digital asset enrollment', function (): void {
    $digitalAsset = DigitalAsset::factory()->create();
    $enrollment   = createEnrollmentForReview($this->user, productable: $digitalAsset);

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [
        'rating'  => 4,
        'title'   => 'Useful asset',
        'comment' => 'Exactly what I needed.',
    ])->assertCreated();

    $this->assertDatabaseHas('reviews', [
        'user_id'         => $this->user->id,
        'reviewable_type' => $digitalAsset->getMorphClass(),
        'reviewable_id'   => $digitalAsset->id,
    ]);
});

it('rejects an enrollment whose productable cannot be reviewed', function (): void {
    $bundle     = Bundle::factory()->create();
    $enrollment = createEnrollmentForReview($this->user, productable: $bundle);

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [
        'rating'  => 4,
        'title'   => 'Nice',
        'comment' => 'I liked it.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['enrollment']);

    expect(Review::query()->count())->toBe(0);
});

it('returns 404 when the enrollment belongs to another customer', function (): void {
    $enrollment = createEnrollmentForReview(User::factory()->create());

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [
        'rating'  => 4,
        'title'   => 'Nice',
        'comment' => 'I liked it.',
    ])
        ->assertNotFound()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);

    expect(Review::query()->count())->toBe(0);
});

it('rejects a rating outside the 1 to 5 range', function (int $rating): void {
    $enrollment = createEnrollmentForReview($this->user);

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [
        'rating'  => $rating,
        'title'   => 'Title',
        'comment' => 'Comment',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating']);

    expect(Review::query()->count())->toBe(0);
})->with([0, 6]);

it('requires rating title and comment', function (): void {
    $enrollment = createEnrollmentForReview($this->user);

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating', 'title', 'comment']);
});

it('rejects a review for a non-active enrollment', function (EnrollmentStatusEnum $status): void {
    $enrollment = createEnrollmentForReview($this->user, $status);

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [
        'rating'  => 4,
        'title'   => 'Nice',
        'comment' => 'I liked it.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['enrollment']);

    expect(Review::query()->count())->toBe(0);
})->with([
    EnrollmentStatusEnum::AWAITING_PAYMENT,
    EnrollmentStatusEnum::SUSPENDED,
    EnrollmentStatusEnum::EXPIRED,
    EnrollmentStatusEnum::CANCELLED,
]);

it('rejects a duplicate review while a live review exists', function (ReviewStatusEnum $status): void {
    $course     = Course::factory()->create();
    $enrollment = createEnrollmentForReview($this->user, productable: $course);

    Review::factory()->create([
        'user_id'         => $this->user->id,
        'reviewable_type' => $course->getMorphClass(),
        'reviewable_id'   => $course->id,
        'status'          => $status,
    ]);

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [
        'rating'  => 4,
        'title'   => 'Nice',
        'comment' => 'I liked it.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['review']);

    expect(Review::query()->count())->toBe(1);
})->with([
    ReviewStatusEnum::PENDING,
    ReviewStatusEnum::APPROVED,
]);

it('allows resubmission after a rejected review', function (): void {
    $course     = Course::factory()->create();
    $enrollment = createEnrollmentForReview($this->user, productable: $course);

    Review::factory()->create([
        'user_id'         => $this->user->id,
        'reviewable_type' => $course->getMorphClass(),
        'reviewable_id'   => $course->id,
        'status'          => ReviewStatusEnum::REJECTED,
    ]);

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [
        'rating'  => 4,
        'title'   => 'Second try',
        'comment' => 'Rewritten.',
    ])->assertCreated();

    expect(Review::query()->count())->toBe(2);
});

it('requires authentication', function (): void {
    $enrollment = createEnrollmentForReview(User::factory()->create());

    $this->app->get('auth')->forgetGuards();

    postJson(route('api.v1.shop.student.courses.review', ['enrollment' => $enrollment->uuid]), [
        'rating'  => 4,
        'title'   => 'Nice',
        'comment' => 'I liked it.',
    ])->assertUnauthorized();
});

/*
 * Mutation notes
 * --------------
 * `SubmitReviewAction::resolveReviewable()` carries three defensive guards whose
 * mutants survive by equivalence, not by a coverage gap:
 * - `loadMissing()` is a query-efficiency guard; lazy loading resolves the same
 *   relations to the same outcome, so removing it changes nothing observable.
 * - both null-safe operators guard a dangling relation the schema forbids:
 *   `enrollments.product_delivery_option_id` is a non-null FK, and a Product always
 *   carries a `productable`.
 */

// ─── Helpers ─────────────────────────────────────────────────────────────────

if (! function_exists('createEnrollmentForReview')) {
    /**
     * Create an enrollment whose product resolves to the given productable.
     *
     * The enrollment status is forced without firing model events so the test
     * setup does not drag provisioning/availability side effects into scope.
     */
    function createEnrollmentForReview(
        User $customer,
        EnrollmentStatusEnum $status = EnrollmentStatusEnum::ACTIVE,
        ?Model $productable = null,
    ): Enrollment {
        $productable ??= Course::factory()->create();

        $product = Product::factory()->create([
            'productable_type' => $productable->getMorphClass(),
            'productable_id'   => $productable->id,
        ]);

        $deliveryOption = ProductDeliveryOption::factory()->create([
            'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE->value,
            'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
            'product_id'       => $product->id,
        ]);

        $enrollment = createEnrollment($customer, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);

        $enrollment->forceFill(['enrollment_status' => $status])->saveQuietly();

        return $enrollment->refresh();
    }
}

<?php

use App\Enums\MorphTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\ProductableEnum;
use App\Enums\ReviewStatusEnum;
use App\Models\Course;
use App\Models\Review;
use App\Models\User;

uses(\Tests\AuthTestTrait::class);
describe('ReviewController', function (): void {
    it('filters reviews', function ($filters, $expectedCount): void {
        $this->authorized_user([PermissionEnum::REVIEW_VIEW_ANY]);

        $user = User::factory()->create([
            'email'      => 'filtered_customer@eaxmple.com',
            'first_name' => 'filtered',
            'last_name'  => 'customer',
        ]);

        Review::factory()->count(5)->create([
            'user_id'         => User::factory(),
            'reviewable_id'   => Course::factory(),
            'reviewable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
            'status'          => ReviewStatusEnum::PENDING->value,
            'is_featured'     => false,
        ]);
        foreach ($filters as $filterKey => $filterValue) {
            if (is_callable($filterValue)) {
                $filters[$filterKey] = $filterValue();
            }
        }
        $key = array_key_first($filters);
        switch ($key) {
            case 'customer_name':
            case 'user_id':
                Review::factory()->count($expectedCount)->create(['user_id' => $user->id]);
                break;
            case 'reviewable_type':
                $type = MorphTypeEnum::COURSE->value;
                Review::factory()->count($expectedCount)->create(['reviewable_type' => $type]);
                break;
            case 'status':
                $status = ReviewStatusEnum::APPROVED->value;
                Review::factory()->count($expectedCount)->create(['status' => $status]);
                break;
            case 'is_featured':
                $isFeatured = true;
                Review::factory()->count($expectedCount)->create(['is_featured' => $isFeatured]);
                break;
        }
        if ($filters) // Make API request with filter
        {
            $response = $this->getJson('/api/v1/admin/review'.'?'.http_build_query(['filter' => $filters]));
        }

        // Assert the response is successful and contains the expected number of items
        $response->assertOk();
        $responseData = $response->json('data.data');
        expect(count($responseData))->toBe($expectedCount);
    })->with([
        'user_id'         => [
            [
                'user_id' =>
                    function () {
                        return User::query()->where('email', 'filtered_customer@eaxmple.com')->first()->id;
                    }
            ],
            3
        ],
        'reviewable_type' => [['reviewable_type' => ProductableEnum::COURSE->value], 4],
        'status'          => [['status' => ReviewStatusEnum::APPROVED->value], 2],
        'is_featured'     => [['is_featured' => '1'], 3],
        'customer_name'   => [['customer_name' => 'filtered customer'], 3]
    ]);

    it('shows list of review', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_VIEW_ANY]);
        Review::factory()->count(5)->create();
        $response = $this->getJson('/api/v1/admin/review');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'reviewable_type',
                        'reviewable_id',
                        'rating',
                        'title',
                        'comment',
                        'status',
                        'is_featured',
                        'reviewable',
                        'user',
                        'created_at',
                        'updated_at',
                    ]
                ],
            ],
        ]);
        $responseData = $response->json('data.data');
        expect(count($responseData))->toBe(5);
    });

    it('shows a review with correct revieable data', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_VIEW]);
        $course = Course::factory()->create();
        $review = Review::factory()->create([
            'reviewable_type' => MorphTypeEnum::COURSE->value,
            'reviewable_id'   => $course->id,
        ]);
        $response = $this->getJson('/api/v1/admin/review/'.$review->id);
        $response->assertOk();
        $responseData = $response->json('data');

        expect($responseData['reviewable'])->not->toBeNull()
            ->and($responseData['reviewable']['id'])->toBe($course->id)
            ->and($responseData['reviewable']['full_name'])->toBe($course->full_name)
            ->and($responseData['reviewable']['short_name'])->toBe($course->short_name)
            ->and($responseData['reviewable_type']['label'])->toBe(MorphTypeEnum::COURSE->translate())
            ->and($responseData['reviewable_type']['value'])->toBe(MorphTypeEnum::COURSE->value);
    });

    it('returns 404 if the review does not exist', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_VIEW]);
        $response = $this->getJson('/api/v1/admin/review/999');
        $response->assertNotFound();
    });

    it('deletes a review', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_DELETE]);
        $review = Review::factory()->create();
        $response = $this->deleteJson('/api/v1/admin/review/'.$review->id);
        $response->assertNoContent();
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    });

    it('returns 404 if the review to delete does not exist', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_DELETE]);
        $response = $this->deleteJson('/api/v1/admin/review/999');
        $response->assertNotFound();
    });
});


describe('ApproveReviewController', function (): void {
    it('approves a review', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_UPDATE]);
        $review = Review::factory()->create(['status' => ReviewStatusEnum::PENDING->value]);
        $response = $this->postJson('/api/v1/admin/review/'.$review->id.'/approve');
        $response->assertOk();
        $response->assertJson([
            'message'  => __('messages.review.approved'),
            'data'     => null,
            'metadata' => [],
        ]);
        $this->assertDatabaseHas('reviews', [
            'id'     => $review->id,
            'status' => ReviewStatusEnum::APPROVED->value,
        ]);
    });

    it('returns 404 if the review does not exist', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_UPDATE]);
        $response = $this->postJson('/api/v1/admin/review/999/approve');
        $response->assertNotFound();
    });
});

describe('RejectReviewController', function (): void {
    it('reject a review', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_UPDATE]);
        $review = Review::factory()->create(['status' => ReviewStatusEnum::PENDING->value]);
        $response = $this->postJson('/api/v1/admin/review/'.$review->id.'/reject');
        $response->assertOk();
        $response->assertJson([
            'message'  => __('messages.review.rejected'),
            'data'     => null,
            'metadata' => [],
        ]);
        $this->assertDatabaseHas('reviews', [
            'id'     => $review->id,
            'status' => ReviewStatusEnum::REJECTED->value,
        ]);
    });

    it('returns 404 if the review does not exist', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_UPDATE]);
        $response = $this->postJson('/api/v1/admin/review/999/reject');
        $response->assertNotFound();
    });
});

describe('UpdateReviewFeaturedStatusController', function (): void {
    it('toggles the featured status of a review when is_featured is not provided', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_UPDATE_FEATURED_STATUS]);
        $review = Review::factory()->create(['is_featured' => false]);
        $response = $this->patchJson('/api/v1/admin/review/'.$review->id.'/featured');
        $response->assertOk();
        $response->assertJson([
            'message'  => __('messages.updated', ['model' => __('messages.models.review')]),
            'data'     => null,
            'metadata' => [],
        ]);
        $this->assertDatabaseHas('reviews', [
            'id'          => $review->id,
            'is_featured' => true,
        ]);

        // Toggle again
        $response = $this->patchJson('/api/v1/admin/review/'.$review->id.'/featured');
        $response->assertOk();
        $this->assertDatabaseHas('reviews', [
            'id'          => $review->id,
            'is_featured' => false,
        ]);
    });

    it('sets the featured status of a review when is_featured is provided', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_UPDATE_FEATURED_STATUS]);
        $review = Review::factory()->create(['is_featured' => false]);
        $response = $this->patchJson('/api/v1/admin/review/'.$review->id.'/featured', [
            'is_featured' => true,
        ]);
        $response->assertOk();
        $response->assertJson([
            'message'  => __('messages.updated', ['model' => __('messages.models.review')]),
            'data'     => null,
            'metadata' => [],
        ]);
        $this->assertDatabaseHas('reviews', [
            'id'          => $review->id,
            'is_featured' => true,
        ]);

        // Set to false
        $response = $this->patchJson('/api/v1/admin/review/'.$review->id.'/featured', [
            'is_featured' => false,
        ]);
        $response->assertOk();
        $this->assertDatabaseHas('reviews', [
            'id'          => $review->id,
            'is_featured' => false,
        ]);
    });

    it('returns 404 if the review does not exist', function (): void {
        $this->authorized_user([PermissionEnum::REVIEW_UPDATE_FEATURED_STATUS]);
        $response = $this->patchJson('/api/v1/admin/review/999/featured');
        $response->assertNotFound();
    });
});

it('returns 403 if the user does not have permission', function (): void {
    $this->authorized_user(); // No permissions
    $review = Review::factory()->create();
    $response = $this->getJson('/api/v1/admin/review');
    $response->assertForbidden();

    $response = $this->getJson('/api/v1/admin/review/'.$review->id);
    $response->assertForbidden();

    $response = $this->deleteJson('/api/v1/admin/review/'.$review->id);
    $response->assertForbidden();

    $response = $this->postJson('/api/v1/admin/review/'.$review->id.'/approve');
    $response->assertForbidden();

    $response = $this->postJson('/api/v1/admin/review/'.$review->id.'/reject');
    $response->assertForbidden();

    $response = $this->patchJson('/api/v1/admin/review/'.$review->id.'/featured');
    $response->assertForbidden();
});

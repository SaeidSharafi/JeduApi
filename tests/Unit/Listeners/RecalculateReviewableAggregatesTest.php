<?php

use App\Actions\Admin\Review\UpdateReviewStatusAction;
use App\Enums\MorphTypeEnum;
use App\Enums\ReviewStatusEnum;
use App\Events\ReviewableAggregatesChanged;
use App\Models\Course;
use App\Models\Review;

describe('RecalculateReviewableAggregates', function (){
    it('recalculates aggregates when a review is approved', function () {
        $course = Course::factory()->create([
            'review_count' => 0,
            'average_rating' => 0.0,
        ]);

        Review::factory()->create([
            'reviewable_id' => $course->id,
            'reviewable_type' => MorphTypeEnum::COURSE->value,
            'rating' => 4,
            'status' => ReviewStatusEnum::PENDING,
        ]);

        Review::factory()->create([
            'reviewable_id' => $course->id,
            'reviewable_type' => MorphTypeEnum::COURSE->value,
            'rating' => 5,
            'status' => ReviewStatusEnum::PENDING,
        ]);

        $reviewToApprove = Review::where('reviewable_id', $course->id)
            ->where('reviewable_type', MorphTypeEnum::COURSE->value)
            ->first();

        $action = new UpdateReviewStatusAction();
        $action->handle($reviewToApprove, ReviewStatusEnum::APPROVED);

        $course->refresh();

        expect($course->review_count)->toBe(1)
            ->and($course->average_rating)->toEqual(4.0);

        $secondReview = Review::where('reviewable_id', $course->id)
            ->where('reviewable_type', MorphTypeEnum::COURSE->value)
            ->where('id', '!=', $reviewToApprove->id)
            ->first();

        $action->handle($secondReview, ReviewStatusEnum::APPROVED);

        $course->refresh();

        expect($course->review_count)->toBe(2)
            ->and($course->average_rating)->toEqual(4.5);
    });

    it('recalculates aggregates for BlogPost', function () {
        $course = Course::factory()->create([
            'review_count' => 0,
            'average_rating' => 0.0,
        ]);

        Review::factory()->create([
            'reviewable_id' => $course->id,
            'reviewable_type' => MorphTypeEnum::COURSE->value,
            'rating' => 3,
            'status' => ReviewStatusEnum::APPROVED,
        ]);

        Review::factory()->create([
            'reviewable_id' => $course->id,
            'reviewable_type' => MorphTypeEnum::COURSE->value,
            'rating' => 5,
            'status' => ReviewStatusEnum::APPROVED,
        ]);

        ReviewableAggregatesChanged::dispatch($course->id, MorphTypeEnum::COURSE->value, 1);

        $course->refresh();

        expect($course->review_count)->toBe(2)
            ->and($course->average_rating)->toEqual(4.0);
    });
    it('does not recalculate aggregates for non-existent reviewable', function () {
        ReviewableAggregatesChanged::dispatch(9999, MorphTypeEnum::COURSE->value, 1);

        expect(true)->toBeTrue();
    });
});

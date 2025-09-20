<?php

use App\Enums\ReviewStatusEnum;

it('update review status', function (): void {
    $review = \App\Models\Review::factory()->create([
        'status' => ReviewStatusEnum::PENDING,
    ]);
    $action = new \App\Actions\Admin\Review\UpdateReviewStatusAction();
    $action->handle($review, ReviewStatusEnum::APPROVED);
    $review->refresh();
    expect($review->status)->toBe(ReviewStatusEnum::APPROVED);
});

it('does not update review status if the same', function (): void {
    $review = \App\Models\Review::factory()->create([
        'status' => ReviewStatusEnum::PENDING,
    ]);
    $action = new \App\Actions\Admin\Review\UpdateReviewStatusAction();
    $createdAt = $review->created_at->format('Y-m-d H:i:s');
    //forward test time by 1 minute
    \Illuminate\Support\Facades\Date::setTestNow(now()->addMinute());

    $action->handle($review, ReviewStatusEnum::PENDING);
    $review->refresh();
    expect($review->status)->toBe(ReviewStatusEnum::PENDING);
    expect($review->created_at->format('Y-m-d H:i:s'))->toBe($createdAt);
});

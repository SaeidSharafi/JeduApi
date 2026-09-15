<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Data\Shop\Student\Review\SubmitReviewData;
use App\Enums\Content\ReviewStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use App\Models\Review;
use App\Models\Seminar;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final readonly class SubmitReviewAction
{
    /**
     * Submit a customer review for the productable behind an enrollment.
     *
     * The review is created PENDING, so staff moderation gates publication. Public
     * aggregates (`review_count` / `average_rating`) only ever count APPROVED reviews
     * and are recomputed by the admin approve/reject actions, never here.
     */
    public function handle(SubmitReviewData $data, Enrollment $enrollment, User $user): Review
    {
        if ($enrollment->enrollment_status !== EnrollmentStatusEnum::ACTIVE) {
            throw ValidationException::withMessages([
                'enrollment' => __('messages.review.enrollment_not_active'),
            ]);
        }

        $productable = $this->resolveReviewable($enrollment);

        if ($productable === null) {
            throw ValidationException::withMessages([
                'enrollment' => __('messages.review.not_reviewable'),
            ]);
        }

        if ($this->hasLiveReview($user, $productable)) {
            throw ValidationException::withMessages([
                'review' => __('messages.review.already_reviewed'),
            ]);
        }

        $review = new Review([
            'rating'      => $data->rating,
            'title'       => $data->title,
            'comment'     => $data->comment,
            'status'      => ReviewStatusEnum::PENDING,
            'is_featured' => false,
        ]);

        $review->user()->associate($user);
        $review->reviewable()->associate($productable);
        $review->save();

        return $review;
    }

    /**
     * Resolve the reviewable productable of an enrollment, mirroring the types the
     * review system supports (Bundle enrollments are component enrollments instead).
     */
    private function resolveReviewable(Enrollment $enrollment): ?Model
    {
        $enrollment->loadMissing('productDeliveryOption.product.productable');

        $productable = $enrollment->productDeliveryOption?->product?->productable;

        if ($productable instanceof Course
            || $productable instanceof Seminar
            || $productable instanceof DigitalAsset
        ) {
            return $productable;
        }

        return null;
    }

    /**
     * A rejected review does not block resubmission; pending and approved reviews do.
     */
    private function hasLiveReview(User $user, Model $productable): bool
    {
        return Review::query()
            ->where('user_id', $user->id)
            ->where('reviewable_type', $productable->getMorphClass())
            ->where('reviewable_id', $productable->getKey())
            ->whereIn('status', [ReviewStatusEnum::PENDING, ReviewStatusEnum::APPROVED])
            ->exists();
    }
}

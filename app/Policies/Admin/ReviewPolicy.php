<?php

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Review;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReviewPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::REVIEW_VIEW_ANY->value);
    }

    public function view(Staff $user, Review $review): bool
    {
        return $user->can(PermissionEnum::REVIEW_VIEW->value);
    }

    public function update(Staff $user, Review $review): bool
    {
        return $user->can(PermissionEnum::REVIEW_UPDATE->value);
    }

    public function delete(Staff $user, Review $review): bool
    {
        return $user->can(PermissionEnum::REVIEW_DELETE->value);
    }

    public function updateFeaturedStatus(Staff $user, Review $review): bool
    {
        return $user->can(PermissionEnum::REVIEW_UPDATE_FEATURED_STATUS->value);
    }
}

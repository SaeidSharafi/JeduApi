<?php

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\CollaborationCarousel;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

class CollaborationCarouselPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::COLLABORATION_CAROUSEL_VIEW_ANY);
    }

    public function view(Staff $user, CollaborationCarousel $collaborationCarousel): bool
    {
        return $user->can(PermissionEnum::COLLABORATION_CAROUSEL_VIEW);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::COLLABORATION_CAROUSEL_CREATE);
    }

    public function update(Staff $user, CollaborationCarousel $collaborationCarousel): bool
    {
        return $user->can(PermissionEnum::COLLABORATION_CAROUSEL_UPDATE);
    }

    public function delete(Staff $user, CollaborationCarousel $collaborationCarousel): bool
    {
        return $user->can(PermissionEnum::COLLABORATION_CAROUSEL_DELETE);
    }
}

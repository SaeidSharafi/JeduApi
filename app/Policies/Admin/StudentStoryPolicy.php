<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use App\Models\StudentStory;
use Illuminate\Auth\Access\HandlesAuthorization;

final class StudentStoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::STUDENT_STORY_VIEW_ANY);
    }

    public function view(Staff $user, StudentStory $studentStory): bool
    {
        return $user->can(PermissionEnum::STUDENT_STORY_VIEW);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::STUDENT_STORY_CREATE);
    }

    public function update(Staff $user, StudentStory $studentStory): bool
    {
        return $user->can(PermissionEnum::STUDENT_STORY_UPDATE);
    }

    public function delete(Staff $user, StudentStory $studentStory): bool
    {
        return $user->can(PermissionEnum::STUDENT_STORY_DELETE);
    }
}

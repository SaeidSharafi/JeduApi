<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\LearningPath;
use App\Models\Staff;

final class LearningPathPolicy
{
    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::LEARNING_PATH_VIEW_ANY->value);
    }

    public function view(Staff $user, LearningPath $learningPath): bool
    {
        return $user->can(PermissionEnum::LEARNING_PATH_VIEW->value);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::LEARNING_PATH_CREATE->value);
    }

    public function update(Staff $user, LearningPath $learningPath): bool
    {
        return $user->can(PermissionEnum::LEARNING_PATH_UPDATE->value);
    }

    public function delete(Staff $user, LearningPath $learningPath): bool
    {
        return $user->can(PermissionEnum::LEARNING_PATH_DELETE->value);
    }
}

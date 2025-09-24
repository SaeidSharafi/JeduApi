<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\HomePageBlock;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class HomePageBlockPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::HOME_PAGE_BLOCK_VIEW_ANY);
    }

    public function view(Staff $user, HomePageBlock $homePageBlock): bool
    {
        return $user->can(PermissionEnum::HOME_PAGE_BLOCK_VIEW);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::HOME_PAGE_BLOCK_CREATE);
    }

    public function update(Staff $user, HomePageBlock $homePageBlock): bool
    {
        return $user->can(PermissionEnum::HOME_PAGE_BLOCK_UPDATE);
    }

    public function delete(Staff $user, HomePageBlock $homePageBlock): bool
    {
        return $user->can(PermissionEnum::HOME_PAGE_BLOCK_DELETE);
    }
}

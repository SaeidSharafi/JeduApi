<?php

declare(strict_types=1);

namespace App\Policies\Admin\Blog;

use App\Enums\PermissionEnum;
use App\Models\Blog\BlogCategory;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class BlogCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->hasPermissionTo(PermissionEnum::BLOG_CATEGORY_VIEW_ANY);
    }

    public function view(Staff $user, BlogCategory $blogCategory): bool
    {
        return $user->hasPermissionTo(PermissionEnum::BLOG_CATEGORY_VIEW);
    }

    public function create(Staff $user): bool
    {
        return $user->hasPermissionTo(PermissionEnum::BLOG_CATEGORY_CREATE);
    }

    public function update(Staff $user, BlogCategory $blogCategory): bool
    {
        return $user->hasPermissionTo(PermissionEnum::BLOG_CATEGORY_UPDATE);
    }

    public function delete(Staff $user, BlogCategory $blogCategory): bool
    {
        return $user->hasPermissionTo(PermissionEnum::BLOG_CATEGORY_DELETE);
    }
}

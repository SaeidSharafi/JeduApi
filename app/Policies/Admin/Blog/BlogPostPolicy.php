<?php

declare(strict_types=1);

namespace App\Policies\Admin\Blog;

use App\Enums\PermissionEnum;
use App\Models\Blog\BlogPost;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class BlogPostPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_VIEW_ANY);
    }

    public function view(Staff $user, BlogPost $blogPost): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_VIEW);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_CREATE);
    }

    public function update(Staff $user, BlogPost $blogPost): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_UPDATE);
    }

    public function delete(Staff $user, BlogPost $blogPost): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_DELETE);
    }
}

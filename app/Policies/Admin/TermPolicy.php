<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use App\Models\Term;

final class TermPolicy
{
    /**
     * Determine whether the user can view any terms.
     */
    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::TERM_VIEW_ANY);
    }

    /**
     * Determine whether the user can view the term.
     */
    public function view(Staff $user, Term $term): bool
    {
        return $user->can(PermissionEnum::TERM_VIEW);
    }

    /**
     * Determine whether the user can create terms.
     */
    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::TERM_CREATE);
    }

    /**
     * Determine whether the user can update the term.
     */
    public function update(Staff $user, Term $term): bool
    {
        return $user->can(PermissionEnum::TERM_UPDATE);
    }

    /**
     * Determine whether the user can delete the term.
     */
    public function delete(Staff $user, Term $term): bool
    {
        return $user->can(PermissionEnum::TERM_DELETE);
    }
}

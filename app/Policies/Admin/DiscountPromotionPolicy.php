<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\DiscountPromotion;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class DiscountPromotionPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::DISCOUNT_VIEW_ANY->value);
    }

    public function view(Staff $user, DiscountPromotion $promotion): bool
    {
        return $user->can(PermissionEnum::DISCOUNT_VIEW->value);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::DISCOUNT_CREATE->value);
    }

    public function update(Staff $user, DiscountPromotion $promotion): bool
    {
        return $user->can(PermissionEnum::DISCOUNT_UPDATE->value);
    }

    public function delete(Staff $user, DiscountPromotion $promotion): bool
    {
        return $user->can(PermissionEnum::DISCOUNT_DELETE->value);
    }
}

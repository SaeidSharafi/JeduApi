<?php

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\ProductDeliveryOption;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductDeliveryOptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::PRODUCT_DELIVERY_OPTION_VIEW_ANY->value);
    }

    public function view(Staff $user, ProductDeliveryOption $productDeliveryOption): bool
    {
        return $user->can(PermissionEnum::PRODUCT_DELIVERY_OPTION_VIEW->value);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE->value);
    }

    public function update(Staff $user, ProductDeliveryOption $productDeliveryOption): bool
    {
        return $user->can(PermissionEnum::PRODUCT_DELIVERY_OPTION_UPDATE->value);
    }

    public function delete(Staff $user, ProductDeliveryOption $productDeliveryOption): bool
    {
        return $user->can(PermissionEnum::PRODUCT_DELIVERY_OPTION_DELETE->value);
    }
}

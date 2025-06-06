<?php

namespace App\Policies;

use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductDeliveryOptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {

    }

    public function view(User $user, ProductDeliveryOption $productDeliveryOption): bool
    {
    }

    public function create(User $user): bool
    {
    }

    public function update(User $user, ProductDeliveryOption $productDeliveryOption): bool
    {
    }

    public function delete(User $user, ProductDeliveryOption $productDeliveryOption): bool
    {
    }

    public function restore(User $user, ProductDeliveryOption $productDeliveryOption): bool
    {
    }

    public function forceDelete(User $user, ProductDeliveryOption $productDeliveryOption): bool
    {
    }
}

<?php

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Slider;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

class SliderPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::SLIDER_VIEW_ANY->value);
    }

    public function view(Staff $user, Slider $slider): bool
    {
        return $user->can(PermissionEnum::SLIDER_VIEW->value);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::SLIDER_CREATE->value);
    }

    public function update(Staff $user, Slider $slider): bool
    {
        return $user->can(PermissionEnum::SLIDER_UPDATE->value);
    }

    public function delete(Staff $user, Slider $slider): bool
    {
        return $user->can(PermissionEnum::SLIDER_DELETE->value);
    }
}

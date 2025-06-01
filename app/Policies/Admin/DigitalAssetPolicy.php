<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use App\Models\DigitalAsset;
use Illuminate\Auth\Access\HandlesAuthorization;

final class DigitalAssetPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $Admin): bool
    {
        return $Admin->can(PermissionEnum::FILE_VIEW_ANY->value);
    }

    public function view(Staff $Admin, DigitalAsset $digitalAsset): bool
    {
        return $Admin->can(PermissionEnum::FILE_VIEW->value);
    }

    public function create(Staff $Admin): bool
    {
        return $Admin->can(PermissionEnum::FILE_CREATE->value);
    }

    public function update(Staff $Admin, DigitalAsset $digitalAsset): bool
    {
        return $Admin->can(PermissionEnum::FILE_UPDATE->value);
    }

    public function delete(Staff $Admin, DigitalAsset $digitalAsset): bool
    {
        return $Admin->can(PermissionEnum::FILE_DELETE->value);
    }
}

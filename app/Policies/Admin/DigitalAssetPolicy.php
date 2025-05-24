<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Admin;
use App\Models\DigitalAsset;
use Illuminate\Auth\Access\HandlesAuthorization;

final class DigitalAssetPolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $Admin): bool
    {
        return $Admin->can(PermissionEnum::FILE_VIEW_ANY->value);
    }

    public function view(Admin $Admin, DigitalAsset $digitalAsset): bool
    {
        return $Admin->can(PermissionEnum::FILE_VIEW->value);
    }

    public function create(Admin $Admin): bool
    {
        return $Admin->can(PermissionEnum::FILE_CREATE->value);
    }

    public function update(Admin $Admin, DigitalAsset $digitalAsset): bool
    {
        return $Admin->can(PermissionEnum::FILE_UPDATE->value);
    }

    public function delete(Admin $Admin, DigitalAsset $digitalAsset): bool
    {
        return $Admin->can(PermissionEnum::FILE_DELETE->value);
    }
}

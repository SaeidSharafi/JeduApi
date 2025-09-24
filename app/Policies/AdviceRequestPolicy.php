<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\AdviceRequest;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class AdviceRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::ADVICE_REQUEST_VIEW_ANY);
    }

    public function view(Staff $user, AdviceRequest $adviceRequest): bool
    {
        return $user->can(PermissionEnum::ADVICE_REQUEST_VIEW);
    }

    public function update(Staff $user, AdviceRequest $adviceRequest): bool
    {
        return $user->can(PermissionEnum::ADVICE_REQUEST_UPDATE);
    }

    public function delete(Staff $user, AdviceRequest $adviceRequest): bool
    {
        return $user->can(PermissionEnum::ADVICE_REQUEST_DELETE);
    }
}

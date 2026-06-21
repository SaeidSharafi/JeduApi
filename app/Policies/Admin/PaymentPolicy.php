<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Payment;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PaymentPolicy
{
    use HandlesAuthorization;

    public function refund(Staff $user, Payment $payment): bool
    {
        return $user->can(PermissionEnum::PAYMENT_UPDATE->value);
    }

    public function deliver(Staff $user, Payment $payment): bool
    {
        return $user->can(PermissionEnum::PAYMENT_UPDATE->value);
    }

    public function reverse(Staff $user, Payment $payment): bool
    {
        return $user->can(PermissionEnum::PAYMENT_DELETE->value);
    }

    public function inquire(Staff $user, Payment $payment): bool
    {
        return $user->can(PermissionEnum::PAYMENT_VIEW->value);
    }

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::PAYMENT_VIEW_ANY->value);
    }
}

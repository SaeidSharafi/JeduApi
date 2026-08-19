<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\RegistrationVelocityExceededException;
use App\Models\User;
use App\Models\UserDevice;

/**
 * Daily registration velocity caps.
 *
 * Two independent dimensions are capped: the number of registrations per IP
 * address and per server-side device hash (sha256 of ip + user_agent). Each
 * successful registration writes one user_devices row, which doubles as the
 * velocity counter windowed to the current day.
 */
final class RegistrationVelocityService
{
    public function fingerprint(?string $ipAddress, ?string $userAgent): string
    {
        return hash('sha256', ($ipAddress ?? '').($userAgent ?? ''));
    }

    public function assertWithinLimits(?string $ipAddress, string $deviceHash): void
    {
        $max        = (int) config('registration_velocity.max_per_day', 3);
        $startOfDay = now()->startOfDay();

        if ($ipAddress !== null) {
            $ipCount = UserDevice::query()
                ->where('ip_address', $ipAddress)
                ->where('created_at', '>=', $startOfDay)
                ->count();

            if ($ipCount >= $max) {
                throw new RegistrationVelocityExceededException;
            }
        }

        $deviceCount = UserDevice::query()
            ->where('device_hash', $deviceHash)
            ->where('created_at', '>=', $startOfDay)
            ->count();

        if ($deviceCount >= $max) {
            throw new RegistrationVelocityExceededException;
        }
    }

    public function record(User $user, ?string $ipAddress, ?string $userAgent): void
    {
        UserDevice::query()->create([
            'user_id'     => $user->id,
            'device_hash' => $this->fingerprint($ipAddress, $userAgent),
            'user_agent'  => $userAgent,
            'ip_address'  => $ipAddress,
        ]);
    }
}

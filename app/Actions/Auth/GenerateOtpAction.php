<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Dto\OtpManager\SentOtpDto;
use App\Enums\OtpType;
use App\Models\Staff;
use App\Models\User;
use App\Services\OtpManagerService;

final class GenerateOtpAction
{
    public function execute(User|Staff $user, OtpType $otpType): SentOtpDto
    {
        $guard = $user instanceof Staff ? 'staff' : 'user';

        return app(OtpManagerService::class)->sendAndRetryCheck($user->phone, $guard, $otpType);
    }
}

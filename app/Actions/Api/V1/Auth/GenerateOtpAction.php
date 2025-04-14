<?php

namespace App\Actions\Api\V1\Auth;

use App\Dto\OtpManager\SentOtpDto;
use App\Enums\OtpType;
use App\Models\Admin;
use App\Models\User;
use App\Services\OtpManagerService;

class GenerateOtpAction
{
    public function execute(User|Admin $user, OtpType $otpType): SentOtpDto
    {
        $guard = $user instanceof Admin ? 'admin' : 'user';

        return app(OtpManagerService::class)->sendAndRetryCheck($user->phone, $guard, $otpType);
    }
}

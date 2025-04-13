<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\Otp;
use App\Models\User;
use App\Models\Admin;
use App\Notifications\Api\V1\Auth\OtpEmailNotification;
use App\Notifications\Api\V1\Auth\OtpSmsNotification;
use App\Services\OtpManagerService;
use Illuminate\Support\Str;

class GenerateOtpAction
{
    public function execute(User|Admin $user, string $purpose): void
    {
        $sentOtp = app(OtpManagerService::class)->sendAndRetryCheck($user->phone,);

        $user->notify(new OtpSmsNotification($sentOtp));
        if ($user->email) {
            $user->notify(new OtpEmailNotification($sentOtp));
        }
    }
}

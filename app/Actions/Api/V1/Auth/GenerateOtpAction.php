<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\User;
use App\Models\Admin;
use App\Notifications\Api\V1\Auth\OtpEmailNotification;
use App\Notifications\Api\V1\Auth\OtpSmsNotification;
use Illuminate\Support\Str;

class GenerateOtpAction
{
    public function execute(User|Admin $user): void
    {
        $otp = Str::random(6);
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $user->notify(new OtpEmailNotification($otp));

        if ($user instanceof User && $user->phone) {
            $user->notify(new OtpSmsNotification($otp));
        }
    }
}

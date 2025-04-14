<?php

namespace App\Listeners;

use App\Events\OtpPrepared;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\Api\V1\Auth\OtpEmailNotification;
use App\Notifications\Api\V1\Auth\OtpSmsNotification;

class SendOtpNotification
{
    /**
     * Handle the event.
     */
    public function handle(OtpPrepared $event): void
    {
        $indentifier = $event->indentifier;
        $guard = $event->guard;
        $otpCode = $event->code;

        $model = $guard === 'admin' ? Admin::class : User::class;
        $user = $model::when(
            filter_var($indentifier, FILTER_VALIDATE_EMAIL),
            fn ($q) => $q->where('email', $indentifier),
            fn ($q) => $q->where('phone', $indentifier)
        )->first();
        if (app()->isLocal()) {
            $user->email = $user->phone.'@example.com';
        }
        $user->notify(new OtpSmsNotification($event));
        if ($user->email) {
            $user->notify(new OtpEmailNotification($otpCode));
        }
    }
}

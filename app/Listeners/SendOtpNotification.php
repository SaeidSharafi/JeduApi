<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OtpPrepared;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\Auth\OtpEmailNotification;
use App\Notifications\Auth\OtpSmsNotification;
use Illuminate\Database\Eloquent\Builder;

final class SendOtpNotification
{
    /**
     * Handle the event.
     */
    public function handle(OtpPrepared $event): void
    {
        $identifier = $event->identifier;
        $guard      = $event->guard;

        $model = $guard === 'staff' ? Staff::class : User::class;
        $user  = $model::when(
            filter_var($identifier, FILTER_VALIDATE_EMAIL),
            fn (Builder $q) => $q->where('email', $identifier),
            fn (Builder $q) => $q->where('phone', $identifier)
        )->first();
        if (app()->isLocal() || app()->environment('testing')) {
            $user->email = $user->phone.'@example.com';
        }
        $user->notify(new OtpSmsNotification($event));
        if ($user->email) {
            $user->notify(new OtpEmailNotification($event));
        }
    }
}

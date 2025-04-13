<?php

namespace App\Traits;

use App\Models\Otp;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasOtp
{
    public function otp(): MorphMany
    {
        return $this->morphMany(Otp::class, 'otpable');
    }
}

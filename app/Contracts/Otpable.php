<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Otpable
{
    public function otp(): MorphMany;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Otp extends Model
{
    protected $fillable = [
        'identifier',
        'type',
        'code',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    public function otpable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }
}

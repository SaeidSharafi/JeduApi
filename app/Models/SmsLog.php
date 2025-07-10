<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'sent_at' => 'datetime:Y-m-d H:i:s',
        ];
    }
}

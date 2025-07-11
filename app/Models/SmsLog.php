<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SmsLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'       => 'array',
            'to'         => 'array',
            'sent_at'    => 'datetime:Y-m-d H:i:s',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }
}

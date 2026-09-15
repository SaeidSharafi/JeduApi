<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SmsLog extends Model
{
    /**
     * Status recorded when no provider call was made: the gateway is switched
     * off, or it has no usable credentials. No HTTP status exists in that case,
     * so the row carries `0` and names the cause in `data.reason`
     * (`gateway_disabled` / `not_configured`); `sent_at` is the attempt time.
     */
    public const int STATUS_SKIPPED = 0;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'       => 'array',
            'to'         => 'array',
            'sent_at'    => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

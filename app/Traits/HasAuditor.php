<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasAuditor
{
    public static function bootHasAuditor(): void
    {
        static::creating(function ($model) {
            $model->created_by = auth()->user()?->id ?: $model->created_by;
        });
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }
}

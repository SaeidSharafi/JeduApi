<?php

namespace App\Models;

use App\Enums\AdviceRequestStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdviceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'status',
        'note',
        'handled_by_id',
    ];

    protected $casts = [
        'status' => AdviceRequestStatusEnum::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function handler(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'handled_by_id');
    }
}

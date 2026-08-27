<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InboundRequestStatusEnum;
use Database\Factories\AdviceRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdviceRequest extends Model
{
    /** @use HasFactory<AdviceRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'phone',
        'status',
        'note',
        'handled_by_id',
    ];

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'handled_by_id');
    }

    protected function casts(): array
    {
        return [
            'status'     => InboundRequestStatusEnum::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

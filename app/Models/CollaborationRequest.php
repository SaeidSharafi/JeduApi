<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InboundRequestStatusEnum;
use Database\Factories\CollaborationRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Plank\Mediable\Mediable;

final class CollaborationRequest extends Model
{
    /** @use HasFactory<CollaborationRequestFactory> */
    use HasFactory;

    use Mediable;

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'department',
        'message',
        'status',
        'note',
        'assigned_to_id',
    ];

    /** @return BelongsTo<Staff, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to_id');
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

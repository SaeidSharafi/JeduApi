<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InboundRequestStatusEnum;
use Database\Factories\ContactUsRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContactUsRequest extends Model
{
    /** @use HasFactory<ContactUsRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'full_name',
        'phone',
        'subject',
        'email',
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

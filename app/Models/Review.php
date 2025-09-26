<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Content\ReviewStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'rating',
        'title',
        'comment',
        'status',
        'is_featured',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'status'      => ReviewStatusEnum::class,
            'is_featured' => 'boolean',
            'rating'      => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }
}

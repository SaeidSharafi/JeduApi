<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class LearningPathStep extends Model
{
    /** @use HasFactory<\Database\Factories\LearningPathStepFactory> */
    use HasFactory;

    protected $fillable = [
        'learning_path_id',
        'position',
        'productable_type',
        'productable_id',
        'title',
        'description',
    ];

    /**
     * @return BelongsTo<LearningPath, $this>
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /**
     * @return MorphTo<Course|Seminar|DigitalAsset, $this>
     */
    public function productable(): MorphTo
    {
        return $this->morphTo(); // @phpstan-ignore return.type (larastan types morphTo() as MorphTo<Model>)
    }

    protected function casts(): array
    {
        return [
            'position'   => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

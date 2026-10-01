<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Content\PublicationStatusEnum;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Plank\Mediable\Mediable;

final class LearningPath extends Model
{
    /** @use HasFactory<\Database\Factories\LearningPathFactory> */
    use HasFactory;

    use HasMedia;
    use Mediable;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'introduction_title',
        'introduction_description',
        'conclusion_title',
        'conclusion_description',
        'thumbnail_url',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'display_order',
        'status',
    ];

    /**
     * @return HasMany<LearningPathStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(LearningPathStep::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'status'        => PublicationStatusEnum::class,
            'display_order' => 'integer',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }
}

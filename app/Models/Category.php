<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Plank\Mediable\Mediable;

final class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use Mediable;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'status',
        'description',
        'image_url',
        'icon_url',
        'color_scheme',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'properties',
        'additional_info',
    ];

    /**
     * @return BelongsToMany<Course,$this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'category_course');
    }

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\PublicationStatusEnum::class,
            'properties' => 'array',
            'additional_info' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

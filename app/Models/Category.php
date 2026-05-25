<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
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
        'educational_calendar_url',
        'color_scheme',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'properties',
        'additional_info',
    ];

    /**
     * @return MorphToMany<Course,$this>
     */
    public function courses(): MorphToMany
    {
        return $this->morphedByMany(Course::class, 'categorizable');
    }

    /**
     * @return MorphToMany<DigitalAsset,$this>
     */
    public function digitalAssets(): MorphToMany
    {
        return $this->morphedByMany(DigitalAsset::class, 'categorizable');
    }

    /**
     * @return MorphToMany<Seminar,$this>
     */
    public function seminars(): MorphToMany
    {
        return $this->morphedByMany(Seminar::class, 'categorizable');
    }

    public function categorizable(): HasMany
    {
        return $this->hasMany(Categorizable::class);
    }

    /**
     * @return MorphToMany<Product,$this>
     */
    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'categorizable');
    }

    /**
     * @return BelongsTo<Category>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class);
    }

    /**
     * @return HasMany<Category>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected function casts(): array
    {
        return [
            'status'          => \App\Enums\Content\PublicationStatusEnum::class,
            'properties'      => 'array',
            'additional_info' => 'array',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
        ];
    }
}

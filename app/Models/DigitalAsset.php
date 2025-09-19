<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ProductableContract;
use App\Contracts\ReviewableContract;
use App\Traits\HasMedia;
use App\Traits\IsProductable;
use Database\Factories\DigitalAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Plank\Mediable\Mediable;

final class DigitalAsset extends Model implements ProductableContract, ReviewableContract
{
    /** @use  HasFactory<DigitalAssetFactory>*/
    use HasFactory;

    use IsProductable;
    use Mediable;
    use HasMedia;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'page_count',
        'duration_seconds',
        'is_attachable_to_course',
        'status',
        'keywords',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'status'                  => \App\Enums\PublicationStatusEnum::class,
        'is_attachable_to_course' => 'boolean',
        'published_at'            => 'datetime',
        // with time zone
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return MorphToMany<Category,$this>
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable', 'categorizables', null, 'category_id');
    }

    /**
     * @return MorphToMany<Course,$this>
     */
    public function courses(): MorphToMany
    {
        return $this->morphedByMany(Course::class, 'assetable');
    }
}

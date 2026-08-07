<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ProductableContract;
use App\Contracts\ReviewableContract;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Traits\HasMedia;
use App\Traits\HasReview;
use App\Traits\IsProductable;
use Database\Factories\DigitalAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Plank\Mediable\Mediable;

/**
 * @implements ProductableContract<DigitalAsset>
 * @implements ReviewableContract<DigitalAsset>
 */
final class DigitalAsset extends Model implements ProductableContract, ReviewableContract
{
    /** @use  HasFactory<DigitalAssetFactory> */
    use HasFactory;

    use HasMedia;
    use HasReview;
    use IsProductable;
    use Mediable;

    protected $fillable
        = [
            'full_name',
            'short_name',
            'slug',
            'thumbnail_url',
            'description',
            'version',
            'page_count',
            'duration_seconds',
            'is_attachable_to_course',
            'difficulty_level',
            'faq',
            'status',
            'keywords',
            'meta_title',
            'meta_description',
            'meta_keywords',
            'published_at',
            'created_by',
        ];

    /**
     * @return MorphToMany<Category, $this>
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable', 'categorizables', null, 'category_id');
    }

    /**
     * @return MorphToMany<Course, $this>
     */
    public function courses(): MorphToMany
    {
        return $this->morphedByMany(Course::class, 'assetable');
    }

    protected function casts(): array
    {
        return [
            'faq'                     => 'array',
            'status'                  => PublicationStatusEnum::class,
            'is_attachable_to_course' => 'boolean',
            'published_at'            => 'datetime',
            'difficulty_level'        => CourseDifficultyLevelEnum::class,
            'created_at'              => 'datetime',
            'updated_at'              => 'datetime',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ProductableContract;
use App\Data\MediaData;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use App\Traits\IsProductable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Plank\Mediable\Media;
use Plank\Mediable\Mediable;

final class Course extends Model implements ProductableContract
{
    /** @use HasFactory<\Database\Factories\CourseFactory> */
    use HasFactory;

    use Mediable;
    use IsProductable;

    protected $fillable
        = [
            'slug',
            'full_name',
            'short_name',
            'description',
            'duration',
            'difficulty_level',
            'career_prospects_text',
            'curriculum_summary_text',
            'outcomes_json',
            'default_teacher_info',
            'additional_info',
            'meta_title',
            'meta_description',
            'meta_keywords',
            'properties',
            'status',
            'created_by',
        ];



    /**
     * @return MorphToMany<Category,$this>
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable', 'categorizables', null, 'category_id');
    }

    /**
     * @return MorphToMany<DigitalAsset,$this>
     */
    public function digitalAssets(): MorphToMany
    {
        return $this->morphToMany(DigitalAsset::class, 'assetable');
    }

    protected function casts(): array
    {
        return [
            'status'                       => PublicationStatusEnum::class,
            'difficulty_level'             => CourseDifficultyLevelEnum::class,
            'outcomes_json'                => 'array',
            'additional_info'              => 'array',
            'properties'                   => 'array',
            'total_video_duration_minutes' => 'integer',
            'created_at'                   => 'datetime:Y-m-d H:i:s',
            'updated_at'                   => 'datetime:Y-m-d H:i:s',
        ];
    }
}

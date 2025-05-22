<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Plank\Mediable\Mediable;

final class Course extends Model
{
    /** @use HasFactory<\Database\Factories\CourseFactory> */
    use HasFactory;

    use Mediable;

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

    protected function casts(): array
    {
        return [
            'status'                       => PublicationStatusEnum::class,
            'difficulty_level'             => CourseDifficultyLevelEnum::class,
            'outcomes_json'                => 'array',
            'additional_info'              => 'array',
            'properties'                   => 'array',
            'total_video_duration_minutes' => 'integer',
            'created_at'                   => 'datetime',
            'updated_at'                   => 'datetime',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }
}

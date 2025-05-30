<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use App\Traits\HasAssets;
use App\Traits\HasAuditor;
use App\Traits\HasCategories;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Mediable;

final class Seminar extends Model
{
    use HasAssets;
    use HasAuditor;
    use HasCategories;
    /** @use HasFactory<\Database\Factories\SeminarFactory> */
    use HasFactory;
    use Mediable;

    protected $fillable = [
        'full_name',
        'short_name',
        'subtitle',
        'slug',
        'description',
        'learning_objectives',
        'target_audience',
        'prerequisites',
        'promo_video_external_url',
        'estimated_duration_desc',
        'level',
        'provides_certificate',
        'faq',
        'keywords',
        'status',
        'created_by',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected function casts(): array
    {
        return [
            'faq'                  => 'array',
            'provides_certificate' => 'boolean',
            'status'               => PublicationStatusEnum::class,
            'level'                => CourseDifficultyLevelEnum::class,
            'created_at'           => 'datetime:Y-m-d H:i:s',
            'updated_at'           => 'datetime:Y-m-d H:i:s',
        ];
    }
}

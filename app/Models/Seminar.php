<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ProductableContract;
use App\Contracts\ReviewableContract;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Blog\BlogPost;
use App\Traits\HasAssets;
use App\Traits\HasAuditor;
use App\Traits\HasCategories;
use App\Traits\HasMedia;
use App\Traits\HasReview;
use App\Traits\IsProductable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Plank\Mediable\Mediable;

final class Seminar extends Model implements ProductableContract, ReviewableContract
{
    use HasAssets;
    use HasAuditor;
    use HasCategories;

    /** @use HasFactory<\Database\Factories\SeminarFactory> */
    use HasFactory;

    use HasMedia;
    use HasReview;
    use IsProductable;
    use Mediable;

    protected $fillable = [
        'full_name',
        'short_name',
        'subtitle',
        'slug',
        'thumbnail_url',
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

    public function blogPosts(): MorphToMany
    {
        return $this->morphToMany(BlogPost::class, 'productable', 'blog_post_productables');
    }

    protected function casts(): array
    {
        return [
            'faq'                  => 'array',
            'provides_certificate' => 'boolean',
            'status'               => PublicationStatusEnum::class,
            'level'                => CourseDifficultyLevelEnum::class,
            'created_at'           => 'datetime',
            'updated_at'           => 'datetime',
        ];
    }
}

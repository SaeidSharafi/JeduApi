<?php

declare(strict_types=1);

namespace App\Models\Blog;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Plank\Mediable\Mediable;
use Database\Factories\Blog\BlogCategoryFactory;

final class BlogCategory extends Model
{
    /** @use HasFactory<\Database\Factories\Blog\BlogCategoryFactory> */
    use HasFactory;
    use HasMedia;
    use Mediable;

    protected $table = 'blog_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'icon',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<BlogPost, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_category', 'blog_category_id', 'blog_post_id');
    }
}

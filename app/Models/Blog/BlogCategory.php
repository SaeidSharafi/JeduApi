<?php

declare(strict_types=1);

namespace App\Models\Blog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Plank\Mediable\Mediable;

class BlogCategory extends Model
{
    use Mediable;
    use HasFactory;

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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_category', 'blog_category_id', 'blog_post_id');
    }
}

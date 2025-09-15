<?php

declare(strict_types=1);

namespace App\Models\Blog;

use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Product;
use App\Models\Review;
use App\Models\Seminar;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Plank\Mediable\Mediable;
use App\Models\Staff;

class BlogPost extends Model
{
    use Mediable;

    protected $table = 'blog_posts';

    protected $fillable
        = [
            'title',
            'slug',
            'body',
            'excerpt',
            'author_id',
            'status',
            'published_at',
            'read_time_minutes',
            'is_featured',
            'main_productable_id',
            'main_productable_type',
        ];

    protected $casts
        = [
            'published_at' => 'datetime',
            'is_featured'  => 'boolean',
        ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'author_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_post_category', 'blog_post_id', 'blog_category_id');
    }

    /**
     * @return MorphToMany<Course,$this>
     */
    public function courses(): MorphToMany
    {
        return $this->morphedByMany(Course::class, 'productable', 'blog_post_productables');
    }

    /**
     * @return MorphToMany<Seminar,$this>
     */
    public function seminars(): MorphToMany
    {
        return $this->morphedByMany(Seminar::class, 'productable', 'blog_post_productables');
    }

    /**
     * @return MorphToMany<DigitalAsset,$this>
     */
    public function digitalAssets(): MorphToMany
    {
        return $this->morphedByMany(DigitalAsset::class, 'productable', 'blog_post_productables');
    }

    public function loadRelatedproducts(): self
    {
        $this->loadMissing(['courses', 'seminars', 'digitalAssets']);
        return $this;
    }
    /**
     * The single, featured productable for this post.
     */
    public function mainProductable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function relatedProductables(): Attribute
    {
        return Attribute::make(
            get: function (): Collection {
                return collect()
                    ->merge($this->relationLoaded('courses') ? $this->courses : collect())
                    ->merge($this->relationLoaded('seminars') ? $this->seminars : collect())
                    ->merge($this->relationLoaded('digitalAssets') ? $this->digitalAssets : collect());
            }
        );
    }

    public function syncRelatedProductables(array $productables): void
    {
        // If the array is empty, detach everything and stop.
        if (empty($productables)) {
            $this->courses()->sync([]);
            $this->seminars()->sync([]);
            $this->digitalAssets()->sync([]);
            return;
        }

        $grouped = collect($productables)->groupBy('type');

        $courseIds = $grouped->get('course', collect())->pluck('id')->all();
        $this->courses()->sync($courseIds);

        $seminarIds = $grouped->get('seminar', collect())->pluck('id')->all();
        $this->seminars()->sync($seminarIds);

        $digitalAssetIds = $grouped->get('digital_asset', collect())->pluck('id')->all();
        $this->digitalAssets()->sync($digitalAssetIds);
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}

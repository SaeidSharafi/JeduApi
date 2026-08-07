<?php

declare(strict_types=1);

namespace App\Models\Blog;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Review;
use App\Models\Seminar;
use App\Models\Staff;
use App\Traits\HasMedia;
use App\Traits\HasReview;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Plank\Mediable\Mediable;
use Database\Factories\Blog\BlogPostFactory;

final class BlogPost extends Model
{
    /** @use HasFactory<\Database\Factories\Blog\BlogPostFactory> */
    use HasFactory;
    use HasMedia;
    use HasReview;
    use Mediable;
    use Searchable;

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
            'thumbnail_url',
            'meta_title',
            'meta_description',
            'meta_keywords',
        ];

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id'           => (string) $this->id,
            'title'        => $this->title,
            'slug'         => $this->slug,
            'body'         => strip_tags($this->body),
            'excerpt'      => $this->excerpt,
            'status'       => $this->status->value,
            'published_at' => $this->published_at?->timestamp,
            'created_at'   => $this->created_at->timestamp,
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function searchableAs(): string
    {
        return 'blog_posts';
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'author_id');
    }

    /**
     * @return BelongsToMany<BlogCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_post_category', 'blog_post_id', 'blog_category_id');
    }

    /**
     * @return MorphToMany<Course,$this>
     */
    /**
     * @return MorphToMany<Course, $this>
     */
    public function courses(): MorphToMany
    {
        return $this->morphedByMany(Course::class, 'productable', 'blog_post_productables');
    }

    /**
     * @return MorphToMany<Seminar,$this>
     */
    /**
     * @return MorphToMany<Seminar, $this>
     */
    public function seminars(): MorphToMany
    {
        return $this->morphedByMany(Seminar::class, 'productable', 'blog_post_productables');
    }

    /**
     * @return MorphToMany<DigitalAsset,$this>
     */
    /**
     * @return MorphToMany<DigitalAsset, $this>
     */
    public function digitalAssets(): MorphToMany
    {
        return $this->morphedByMany(DigitalAsset::class, 'productable', 'blog_post_productables');
    }

    public function loadRelatedproductables(): self
    {
        $this->loadMissing(['courses', 'seminars', 'digitalAssets']);

        return $this;
    }

    /**
     * The single, featured productable for this post.
     */
    /**
     * @return MorphTo<Model, $this>
     */
    public function mainProductable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  array<int, array{id: int, type: string}>|null  $productables
     */
    public function syncRelatedProductables(?array $productables): void
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

    // Reviews relation
    /**
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /** @return Attribute<Collection<int, mixed>, never> */
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

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured'  => 'boolean',
            'status'       => PublicationStatusEnum::class,
        ];
    }
}

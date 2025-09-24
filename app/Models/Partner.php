<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Admin\MediaData;
use App\Enums\PartnerShowInEnum;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Plank\Mediable\Media;
use Plank\Mediable\Mediable;

final class Partner extends Model
{
    use HasFactory, Mediable;

    protected $fillable
        = [
            'title',
            'caption',
            'image_id',
            'image_url',
            'image_alt',
            'url',
            'show_in',
            'order',
            'is_active',
        ];

    /**
     * Define attribute casts for the model.
     *
     * Returns an array mapping attribute names to their cast types. In this model
     * the `show_in` attribute is cast to the PartnerShowInEnum enum class.
     *
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'show_in' => PartnerShowInEnum::class,
        ];
    }

    /**
     * Scope a query to only include active partners.
     *
     * Applies a `where('is_active', true)` filter to the provided query builder.
     *
     * @param Builder $query Eloquent query builder instance to be scoped.
     * @return Builder The modified query builder.
     */
    #[Scope]
    public function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the first `image` media converted to a MediaData DTO if the `media` relation is already loaded.
     *
     * If the `media` relation is not loaded this method returns null. When loaded, it maps each
     * Media model from the `image` collection through MediaData::fromModel(...) and returns the first result.
     *
     * @return MediaData|null The first `image` media as MediaData, or null if the relation is not loaded or no image exists.
     */
    public function getImage(): ?MediaData
    {
        if ($this->relationLoaded('media')) {
            return $this->getMedia('image')
                ->map(fn(Media $m): MediaData => MediaData::fromModel($m, 'image'))
                ->first();
        }
        return null;
    }
}

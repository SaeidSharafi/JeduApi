<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Admin\MediaData;
use App\Enums\Content\PartnerShowInEnum;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Media;
use Plank\Mediable\Mediable;
use Database\Factories\PartnerFactory;

final class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getImage(): ?MediaData
    {
        if ($this->relationLoaded('media')) {
            return $this->getMedia('image')
                ->map(fn (Media $m): MediaData => MediaData::fromModel($m, 'image'))
                ->first();
        }

        return null;
    }

    protected function casts(): array
    {
        return [
            'show_in' => PartnerShowInEnum::class,
        ];
    }
}

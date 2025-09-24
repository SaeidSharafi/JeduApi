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

    protected function casts(): array
    {
        return [
            'show_in' => PartnerShowInEnum::class,
        ];
    }

    #[Scope]
    public function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

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

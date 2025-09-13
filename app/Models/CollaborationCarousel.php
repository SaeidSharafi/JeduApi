<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Admin\MediaData;
use App\Enums\CollaborationCarouselShowInEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Plank\Mediable\Media;
use Plank\Mediable\Mediable;

final class CollaborationCarousel extends Model
{
    use HasFactory, Mediable;

    protected $fillable = [
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
            'show_in' => CollaborationCarouselShowInEnum::class,
        ];
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

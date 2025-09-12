<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Admin\MediaData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Plank\Mediable\Media;
use Plank\Mediable\Mediable;

final class Slider extends Model
{
    use HasFactory, Mediable;

    protected $fillable
        = [
            'title',
            'caption',
            'image_id',
            'image_url',
            'image_alt',
            'link',
            'order',
        ];

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

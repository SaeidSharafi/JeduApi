<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Admin\MediaData;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
            'status',
            'link',
            'order',
        ];

    public function getImage(): ?MediaData
    {
        if ($this->relationLoaded('media')) {
            return $this->getMedia('image')
                ->map(fn (Media $m): MediaData => MediaData::fromModel($m, 'image'))
                ->first();
        }

        return null;
    }

    #[Scope]
    public function active(Builder $query): Builder
    {
        return $query->where('status', \App\Enums\Content\PublicationStatusEnum::PUBLISHED);
    }

    protected function casts(): array
    {
        return [
            'status'     => \App\Enums\Content\PublicationStatusEnum::class,
            'order'      => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

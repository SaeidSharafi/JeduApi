<?php

declare(strict_types=1);

namespace App\Data;

use Plank\Mediable\Media;
use Spatie\LaravelData\Data;

/**
 * @property int $id
 * @property string $url
 * @property int $size
 * @property string $file_name
 * @property string $alt
 * @property string $mime_type
 * @property string $extension
 * @property string $tag
 */
final class MediaData extends Data
{
    public function __construct(
        public int $id,
        public string $url,
        public int $size,
        public string $file_name,
        public string $alt,
        public string $mime_type,
        public string $extension,
        public ?string $tag,
        public ?ThumbnailData $thumbnail,
    ) {}

    public static function fromModel(Media $media, ?string $tag = null): self
    {
        $thumbnail = null;
        if ($media->isOriginal()) {
            if ($media->relationLoaded('variants')) {
                $thumbnail = $media->findVariant('thumb');
                $thumbnail = $thumbnail ? ThumbnailData::fromModel($thumbnail) : null;
            }

        }

        return new self(
            $media->id,
            $media->getUrl(),
            $media->size,
            $media->filename,
            $media->getAttribute('alt'),
            $media->mime_type,
            $media->extension,
            $tag,
            $thumbnail
        );
    }
}

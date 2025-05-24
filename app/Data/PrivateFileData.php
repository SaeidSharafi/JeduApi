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
final class PrivateFileData extends Data
{
    public function __construct(
        public int $id,
        public string $url,
        public int $size,
        public string $file_name,
        public string $alt,
        public string $mime_type,
        public string $extension,
        public ?string $tag = null,
    ) {}

    public static function fromModel(Media $media, ?string $tag = null): self
    {
        $url = route('api.v1.admin.private-upload.download', ['file' => $media->id]);

        return new self(
            $media->id,
            $url,
            $media->size,
            $media->filename,
            $media->getAttribute('alt'),
            $media->mime_type,
            $media->extension,
            $tag,
        );
    }
}

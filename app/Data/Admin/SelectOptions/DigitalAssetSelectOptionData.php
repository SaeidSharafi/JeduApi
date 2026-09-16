<?php

declare(strict_types=1);

namespace App\Data\Admin\SelectOptions;

use App\Enums\MediaTagEnum;
use App\Models\DigitalAsset;
use Plank\Mediable\Media;
use Spatie\LaravelData\Data;

final class DigitalAssetSelectOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $subtitle,
        public string $image_url,
    ) {}

    public static function fromModel(DigitalAsset $digitalAsset): self
    {
        return new self(
            id: $digitalAsset->id,
            title: $digitalAsset->full_name,
            subtitle: self::fileSummary($digitalAsset),
            image_url: $digitalAsset->thumbnail_url ?? '',
        );
    }

    /**
     * Summarise the asset's single main file as "<TYPE> · <SIZE>".
     */
    private static function fileSummary(DigitalAsset $digitalAsset): string
    {
        $file = $digitalAsset->getMedia(MediaTagEnum::MAIN->value)->first();

        if (! $file instanceof Media) {
            return '';
        }

        $parts = [];

        if (filled($file->extension)) {
            $parts[] = mb_strtoupper($file->extension);
        }

        if (filled($file->size)) {
            $parts[] = formatFileSize($file->size);
        }

        return implode(' · ', $parts);
    }
}

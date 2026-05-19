<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses\Blocks;

use Spatie\LaravelData\Data;

final class DigitalAssetFileData extends Data
{
    public function __construct(
        public int $id,
        public ?string $short_name,
        public ?string $full_name,
        public ?string $thumbnail_url,
        public string $download_url,
    ) {}
}

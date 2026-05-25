<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses\Blocks;

use Spatie\LaravelData\Data;

final class VideoPlatformSpotplayerBlockData extends Data
{
    public function __construct(
        public ?string $license_key,
        public ?string $player_url,
    ) {}
}

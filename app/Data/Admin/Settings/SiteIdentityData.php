<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;

final class SiteIdentityData extends Data
{
    public function __construct(
        public string $site_title,
        public ?MediaData $favicon = null,
        public ?string $favicon_url = null,
        public ?string $home_h1 = null,
        public ?string $home_description = null,
        public ?string $home_meta_title = null,
        public ?string $home_meta_description = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function getDefaults(): array
    {
        return [
            'site_title'  => 'Jedu',
            'favicon'     => null,
            'favicon_url' => null,
            'home_h1' => null,
            'home_description' => null,
            'home_meta_title' => null,
            'home_meta_description' => null,
        ];
    }
}

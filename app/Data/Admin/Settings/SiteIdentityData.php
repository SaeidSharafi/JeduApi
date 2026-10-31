<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;

/**
 * The site identity rendered in page metadata (browser tab title, Open Graph
 * `site_name`, favicon) and not in the page body.
 */
final class SiteIdentityData extends Data
{
    public function __construct(
        public string $site_title,
        public ?MediaData $favicon = null,
        public ?string $favicon_url = null,
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
        ];
    }
}

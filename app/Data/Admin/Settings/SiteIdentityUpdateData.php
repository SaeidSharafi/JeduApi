<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class SiteIdentityUpdateData extends Data
{
    public function __construct(
        public string $site_title,
        public ?int $favicon = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'site_title' => ['required', 'string', 'max:100'],
            'favicon'    => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'site_title' => [
                'description' => 'Title shown in the browser tab and in Open Graph site_name.',
                'example'     => 'جهاد دانشگاهی قزوین',
            ],
            'favicon' => [
                'description' => 'Media ID of the favicon. It must be an image (ico, png or svg).',
                'example'     => 302,
            ],
        ];
    }
}

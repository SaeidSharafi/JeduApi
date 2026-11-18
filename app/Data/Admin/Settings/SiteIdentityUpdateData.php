<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class SiteIdentityUpdateData extends Data
{
    public function __construct(
        public string $site_title,
        public ?int $favicon = null,
        public string|Optional|null $home_h1 = new Optional(),
        public string|Optional|null $home_description = new Optional(),
        public string|Optional|null $home_meta_title = new Optional(),
        public string|Optional|null $home_meta_description = new Optional(),
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'site_title' => ['required', 'string', 'max:100'],
            'favicon'    => ['nullable', 'integer', 'exists:media,id'],
            'home_h1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'home_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'home_meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'home_meta_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
            'home_h1' => ['description' => 'Visible homepage heading.', 'example' => 'آموزش برای همه'],
            'home_description' => ['description' => 'Plain-text homepage introduction, rendered inline.', 'example' => 'مهارت‌های تازه بیاموزید.'],
            'home_meta_title' => ['description' => 'Homepage document title.', 'example' => 'آموزش | Jedu'],
            'home_meta_description' => ['description' => 'Homepage meta description.', 'example' => 'دوره‌های آموزشی را کشف کنید.'],
        ];
    }
}

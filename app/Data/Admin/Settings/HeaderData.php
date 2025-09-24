<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;

final class HeaderData extends Data
{
    public function __construct(
        public ?MediaData $logo,
        public array $navigation_links,
        public string $contact_phone,
        public string $contact_email
    ) {}

    public static function getDefaults(): array
    {
        return [
            'logo'             => null,
            'logo_url'         => null,
            'navigation_links' => [
                ['title' => 'درباره ما', 'url' => '/about-us'],
                ['title' => 'ارتباط با ما', 'url' => '/contact-us'],
                ['title' => 'کتب و جزوات', 'url' => '/books'],
                ['title' => 'وبینارها', 'url' => '/webinars'],
                ['title' => 'مدرک بین المللی', 'url' => '/international-certificate'],
                ['title' => 'استعلام مدرک', 'url' => '/certificate-verification'],
                ['title' => 'بلاگ', 'url' => '/blog'],
                ['title' => 'دوره‌ها', 'url' => '/courses'],
            ],
            'contact_phone' => '+98-21-12345678',
            'contact_email' => 'info@jedu.ir',
        ];
    }
}

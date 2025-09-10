<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class FooterData extends Data
{
    public function __construct(
        public ?MediaData $logo,
        public string $caption,
        public string $support_link,
        public string $support_email_address,
        public array $addresses,
        public array $categories,
        public array $main_links,
        #[DataCollectionOf(SocialMediaLinkData::class)]
        public DataCollection $social_media_links,
        public array $certifications // array of HTML strings
    ) {}

    public static function getDefaults(): array
    {
        return [
            'logo' => null,
            'caption' => 'Your partner in modern education.',
            'support_link' => '/contact-us',
            'support_email_address' => 'support@jedu.ir',
            'addresses' => [],
            'categories' => [],
            'main_links' => [
                ['title' => 'About Us', 'link' => '/about-us'],
                ['title' => 'Blog', 'link' => '/blog'],
                ['title' => 'Contact Us', 'link' => '/contact-us'],
                ['title' => 'Rules', 'link' => '/rules'],
            ],
            'social_media_links' => [],
            'certifications' => [],
        ];
    }
}

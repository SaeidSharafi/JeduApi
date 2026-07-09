<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Admin\MediaData;
use App\Models\Category;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class FooterData extends Data
{
    public function __construct(
        public ?MediaData $logo,
        public string $caption,
        public string $support_email_address,
        public array $addresses,
        public array $categories,
        #[DataCollectionOf(SocialMediaLinkData::class)]
        public DataCollection $social_media_links,
        public array $certifications // array of HTML strings
    ) {}

    public static function getDefaults(): array
    {
        return [
            'logo'                  => null,
            'logo_url'              => 'https://jedu.ir/images/logo-text.png',
            'logo_alt'              => 'جهاددانشگاهی قزوین',
            'caption'               => 'شریک شما در آموزش مدرن',
            'support_email_address' => 'support@jedu.ir',
            'addresses'             => ContactInfoData::getDefaults()['addresses'],
            'categories'            => Category::query()->get(['name', 'slug'])->toArray(),
            'social_media_links' => ContactInfoData::getDefaults()['social_media_links'],
            'certifications'     => [
                ['name' => 'اینماد', 'image' => 'https://jedu.ir/enamd.png', 'html' => ''],
                ['name' => 'ساماندهی', 'image' => 'https://jedu.ir/enamd.png', 'html' => ''],
            ],
        ];
    }
}

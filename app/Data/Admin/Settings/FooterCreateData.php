<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class FooterCreateData extends Data
{
    public function __construct(
        public ?int $logo,
        public string $caption,
        public string $support_link,
        public string $support_email_address,
        public array $addresses,
        public array $categories,
        public array $main_links,
        #[DataCollectionOf(SocialMediaLinkData::class)]
        public DataCollection $social_media_links,
        public ?array $certifications
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'logo'                   => ['nullable', 'integer', 'exists:media,id'],
            'caption'                => ['required', 'string', 'max:255'],
            'support_link'           => ['required', 'string', 'max:255'],
            'support_email_address'  => ['required', 'string', 'email', 'max:255'],
            'addresses'              => ['required', 'array'],
            'categories'             => ['required', 'array'],
            'categories.*'           => ['integer', 'exists:categories,id'],
            'main_links'             => ['required', 'array'],
            'main_links.*.title'     => ['required', 'string', 'max:255'],
            'main_links.*.link'      => ['required', 'string', 'max:255'],
            'social_media_links'     => ['required', 'array'],
            'certifications'         => ['nullable', 'array'],
            'certifications.*.name'  => ['required', 'string'],
            'certifications.*.image' => ['nullable', 'integer', 'exists:media,id'],
            'certifications.*.html'  => ['nullable', 'string'],
        ];
    }
}

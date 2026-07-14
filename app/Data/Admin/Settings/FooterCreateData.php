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
        public string $support_email_address,
        public array $addresses,
        public array $categories,
        #[DataCollectionOf(SocialMediaLinkData::class)]
        public DataCollection $social_media_links,
        public ?array $certifications
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'logo'                          => ['nullable', 'integer', 'exists:media,id'],
            'caption'                       => ['required', 'string', 'max:255'],
            'support_email_address'         => ['required', 'string', 'email', 'max:255'],
            'addresses'                     => ['required', 'array'],
            'categories'                    => ['required', 'array'],
            'categories.*'                  => ['integer', 'exists:categories,id'],
            'social_media_links'            => ['required', 'array'],
            'social_media_links.*.platform' => ['required', 'string', 'max:100'],
            'social_media_links.*.link'     => ['required', 'string', 'max:255'],
            'certifications'                => ['nullable', 'array'],
            'certifications.*.name'         => ['required', 'string'],
            'certifications.*.image'        => ['nullable', 'integer', 'exists:media,id'],
            'certifications.*.html'         => ['nullable', 'string'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'categories.*.integer'                   => __('validation.custom.footer.categories.integer'),
            'categories.*.exists'                    => __('validation.custom.footer.categories.exists'),
            'social_media_links.*.platform.required' => __('validation.custom.footer.social_media_links.platform.required'),
            'social_media_links.*.link.required'     => __('validation.custom.footer.social_media_links.link.required'),
            'certifications.*.name.required'         => __('validation.custom.footer.certifications.name.required'),
            'certifications.*.image.integer'         => __('validation.custom.footer.certifications.image.integer'),
            'certifications.*.image.exists'          => __('validation.custom.footer.certifications.image.exists'),
            'certifications.*.html.string'           => __('validation.custom.footer.certifications.html.string'),
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
            'logo' => [
                'description' => 'The ID of the logo image (media).',
                'example'     => 12,
            ],
            'caption' => [
                'description' => 'Footer caption text.',
                'example'     => 'Shop with confidence!',
            ],
            'support_link' => [
                'description' => 'URL for support page.',
                'example'     => 'https://support.example.com',
            ],
            'support_email_address' => [
                'description' => 'Support contact email address.',
                'example'     => 'support@example.com',
            ],
            'addresses' => [
                'description' => 'Array of address strings to display in the footer.',
                'example'     => ['123 Main St', '456 Elm St'],
            ],
            'categories' => [
                'description' => 'Array of category IDs to display in the footer.',
                'example'     => [1, 2, 3],
            ],
            'categories.*' => [
                'description' => 'A category ID.',
                'example'     => 1,
            ],
            'main_links' => [
                'description' => 'Array of main link objects for the footer.',
                'example'     => [
                    ['title' => 'Home', 'link' => '/home'],
                    ['title' => 'About Us', 'link' => '/about'],
                ],
            ],
            'main_links.*.title' => [
                'description' => 'The display text for the link.',
                'example'     => 'Home',
            ],
            'main_links.*.link' => [
                'description' => 'The URL for the link.',
                'example'     => '/home',
            ],
            'social_media_links' => [
                'description' => 'Array of social media link objects.',
                'example'     => [
                    ['platform' => 'Instagram', 'url' => 'https://instagram.com/shop'],
                    ['platform' => 'Twitter', 'url' => 'https://twitter.com/shop'],
                ],
            ],
            'social_media_links.*.platform' => [
                'description' => 'Social media platform name.',
                'example'     => 'Instagram',
            ],
            'social_media_links.*.link' => [
                'description' => 'URL for the social media profile.',
                'example'     => 'https://instagram.com/shop',
            ],
            'certifications' => [
                'description' => 'Array of certification objects.',
                'example'     => [
                    ['name' => 'SSL Secure', 'image' => 5, 'html' => '<img src="...">'],
                ],
            ],
            'certifications.*.name' => [
                'description' => 'Certification name.',
                'example'     => 'SSL Secure',
            ],
            'certifications.*.image' => [
                'description' => 'Media ID for the certification image.',
                'example'     => 5,
            ],
            'certifications.*.html' => [
                'description' => 'Optional HTML snippet for the certification.',
                'example'     => '<img src="/cert.png">',
            ],
        ];
    }
}

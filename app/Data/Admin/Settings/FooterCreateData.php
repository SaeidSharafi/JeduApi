<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class FooterCreateData extends Data
{
    /**
     * Create a FooterCreateData instance.
     *
     * @param int|null $logo Optional media ID for the footer logo.
     * @param string $caption Footer caption text (max 255 chars).
     * @param string $support_link Support URL or link text (max 255 chars).
     * @param string $support_email_address Support email address (max 255 chars).
     * @param array $addresses Array of address entries.
     * @param int[] $categories Array of category IDs.
     * @param array $main_links Array of main link entries; each item must include `title` and `link`.
     * @param \Spatie\LaravelData\DataCollection<\App\Data\Admin\Settings\SocialMediaLinkData> $social_media_links Collection of social media link data items.
     * @param array|null $certifications Optional array of certification objects; each may contain `name` (string), `image` (nullable media ID), and `html` (nullable string).
     */
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
    )
    {
    }

    /**
     * Return validation rules for creating footer settings.
     *
     * Provides an associative array of Laravel validation rules for each expected input field,
     * including nested rules for arrays like categories, main_links, social_media_links, and the
     * optional certifications collection.
     *
     * @param ValidationContext $context Context of validation (can be used to adjust rules based on caller/context).
     * @return array<string, mixed> Mapping of input field names to their validation rule(s).
     */
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

<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class SocialMediaLinkData extends Data
{
    public function __construct(
        public string $platform,
        public string $link,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'platform' => ['required', 'string', 'max:50'],
            'link'     => ['required', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(...$args): array
    {
        return [
            'platform' => __('validation.attributes.social_media.platform'),
            'link'     => __('validation.attributes.social_media.link'),
        ];
    }
}

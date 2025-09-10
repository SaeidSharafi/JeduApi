<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AboutUsBlockData extends Data
{
    public function __construct(
        public string $title,
        public string $content,
        public ?string $image = null,
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'image'   => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(...$args): array
    {
        return [
            'title'   => __('validation.attributes.about_us_block.title'),
            'content' => __('validation.attributes.about_us_block.content'),
            'image'   => __('validation.attributes.about_us_block.image'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\HomePageBlock;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class BannerBlockContentData extends Data
{
    public function __construct(
        public int $image_id,
        public string $image_url,
        public string $action,
        public string $action_title,
        public ?string $content,
        public ?string $preset,
    ) {}

    /**
     * @codeCoverageIgnore
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'image_id'     => ['required', 'integer', 'exists:media,id'],
            'action'       => ['required', 'string'],
            'action_title' => ['required', 'string'],
            'content'      => ['nullable', 'string'],
            'preset'       => ['nullable', 'string'],
        ];
    }
}

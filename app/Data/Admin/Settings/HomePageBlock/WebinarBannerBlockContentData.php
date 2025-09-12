<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\HomePageBlock;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class WebinarBannerBlockContentData extends Data
{
    public function __construct(
        public int $product_id,
        public string $text,
        public int $image_id,
        public string $image_url,
    ) {}

    /**
     * @codeCoverageIgnore
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'text'       => ['required', 'string', 'max:255'],
            'image_id'   => ['required', 'integer', 'exists:media,id'],
        ];
    }
}

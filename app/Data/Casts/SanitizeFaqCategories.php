<?php

declare(strict_types=1);

namespace App\Data\Casts;

use App\Enums\Content\RichTextLevelEnum;
use App\Services\RichText\RichTextSanitizer;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final class SanitizeFaqCategories implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sanitizer = app(RichTextSanitizer::class);

        foreach ($value as &$category) {
            if (! is_array($category) || ! isset($category['items']) || ! is_array($category['items'])) {
                continue;
            }

            foreach ($category['items'] as &$item) {
                if (is_array($item) && isset($item['answer']) && is_string($item['answer'])) {
                    $item['answer'] = $sanitizer->sanitize($item['answer'], RichTextLevelEnum::INLINE);
                }
            }
            unset($item);
        }
        unset($category);

        return $value;
    }
}

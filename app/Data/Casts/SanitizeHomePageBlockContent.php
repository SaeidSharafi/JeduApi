<?php

declare(strict_types=1);

namespace App\Data\Casts;

use App\Enums\Content\HomePageBlockTypeEnum;
use App\Enums\Content\RichTextLevelEnum;
use App\Services\RichText\RichTextSanitizer;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Sanitizes the rich key of a home page block payload.
 *
 * The rich key is chosen by the block's declared type — `content` for a
 * banner, `text` for a webinar banner, from `rich-text.home_page_block_keys` —
 * never by which key happens to be present, and always at the `inline` level.
 * Every other block type and every other key passes through byte-identical,
 * because the action mutates `image_url` in the returned array afterwards.
 *
 * Attached only to `HomePageBlockCreateData::$content`, never to
 * `BannerBlockContentData` / `WebinarBannerBlockContentData`, which are never
 * hydrated from a request.
 */
final readonly class SanitizeHomePageBlockContent implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $richKey = $this->richKeyFor($this->typeFrom($properties['type'] ?? null));

        if ($richKey === null || ! isset($value[$richKey]) || ! is_string($value[$richKey])) {
            return $value;
        }

        $value[$richKey] = app(RichTextSanitizer::class)->sanitize($value[$richKey], RichTextLevelEnum::INLINE);

        return $value;
    }

    /**
     * The sibling type follows the client's JSON key order, so it may still be
     * a raw string when this cast runs.
     */
    private function typeFrom(mixed $type): ?HomePageBlockTypeEnum
    {
        if ($type instanceof HomePageBlockTypeEnum) {
            return $type;
        }

        return is_string($type) ? HomePageBlockTypeEnum::tryFrom($type) : null;
    }

    private function richKeyFor(?HomePageBlockTypeEnum $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $richKey = config('rich-text.home_page_block_keys.'.$type->value);

        return is_string($richKey) ? $richKey : null;
    }
}

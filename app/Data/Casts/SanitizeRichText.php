<?php

declare(strict_types=1);

namespace App\Data\Casts;

use App\Enums\Content\RichTextLevelEnum;
use App\Services\RichText\RichTextSanitizer;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Sanitizes a rich text string against the level declared at the property.
 *
 * Attached only to write DTO properties, never to serve DTOs and never as a
 * model cast: hydration of the write class is the inbound boundary, so the
 * read path costs nothing and served bytes always match stored bytes.
 */
final readonly class SanitizeRichText implements Cast
{
    public function __construct(private readonly RichTextLevelEnum $level) {}

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return app(RichTextSanitizer::class)->sanitize($value, $this->level);
    }
}

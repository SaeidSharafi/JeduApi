<?php

declare(strict_types=1);

namespace App\Data\Casts;

use App\Enums\Content\RichTextLevelEnum;
use App\Services\RichText\RichTextSanitizer;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Sanitizes the rich value at one key of every element of an array of rows.
 *
 * FAQ answers, organization-page FAQs and footer certifications are stored as
 * arrays of associative arrays, so a string cast cannot reach the rich value.
 * This cast rewrites only `$key` of each element and returns every other key
 * byte-identical; an element that is not an array, does not carry the key, or
 * carries a non-string value is passed through unchanged (bundle FAQs are not
 * validated, so the cast cannot assume the key exists).
 *
 * Attached only to write DTO properties, never to serve DTOs and never as a
 * model cast: hydration of the write class is the inbound boundary.
 */
final readonly class SanitizeRichTextArray implements Cast
{
    public function __construct(private string $key, private RichTextLevelEnum $level) {}

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sanitizer = app(RichTextSanitizer::class);

        foreach ($value as $index => $element) {
            if (! is_array($element) || ! array_key_exists($this->key, $element) || ! is_string($element[$this->key])) {
                continue;
            }

            $value[$index][$this->key] = $sanitizer->sanitize($element[$this->key], $this->level);
        }

        return $value;
    }
}

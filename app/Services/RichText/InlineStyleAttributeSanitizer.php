<?php

declare(strict_types=1);

namespace App\Services\RichText;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Filters an inline `style` attribute property by property, and element by
 * element.
 *
 * Runs inside the Symfony sanitizer as an attribute sanitizer, so a removed
 * property never reaches storage instead of being cleaned afterwards. The
 * allowed properties and the elements they may appear on come from
 * `config/rich-text.php`.
 */
final readonly class InlineStyleAttributeSanitizer implements AttributeSanitizerInterface
{
    /**
     * @param  list<string>  $properties
     * @param  array<string, list<string>>  $elements
     */
    public function __construct(
        private array $properties,
        private array $elements,
    ) {}

    /**
     * The per-property element map below is what restricts an element.
     */
    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $declarations = [];

        foreach (explode(';', $value) as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $property      = mb_strtolower(mb_trim($parts[0]));
            $propertyValue = mb_trim($parts[1]);

            if ($propertyValue === '' || ! $this->isAllowedOn($property, $element)) {
                continue;
            }

            if (array_key_exists($property, $declarations) || ! $this->isSafeValue($propertyValue)) {
                continue;
            }

            $declarations[$property] = $property.': '.$propertyValue;
        }

        return $declarations === [] ? null : implode('; ', $declarations);
    }

    private function isAllowedOn(string $property, string $element): bool
    {
        if (! in_array($property, $this->properties, true)) {
            return false;
        }

        return in_array($element, $this->elements[$property] ?? [], true);
    }

    /**
     * Rejects values that could escape the declaration or load a resource.
     */
    private function isSafeValue(string $value): bool
    {
        return preg_match('/[<>{}"\'\\\\]|url\s*\(|expression\s*\(|javascript:|@import/i', $value) !== 1;
    }
}

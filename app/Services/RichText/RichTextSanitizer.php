<?php

declare(strict_types=1);

namespace App\Services\RichText;

use App\Enums\Content\RichTextLevelEnum;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes a rich text value against the policy in `config/rich-text.php`.
 *
 * The whole policy lives in that file: tags per level, attributes per element,
 * inline CSS properties, URL schemes, dropped elements, the input cap and the
 * pre-pass rewrites. This class only reads it.
 *
 * Rich values are sanitized once, on the write path, from the write DTO cast
 * (`App\Data\Casts\SanitizeRichText`). Read paths never call this class.
 */
final class RichTextSanitizer
{
    /**
     * @var array<string, HtmlSanitizer>
     */
    private array $sanitizers = [];

    /**
     * Sanitizes the given HTML against the given level.
     *
     * `null` and the empty string are returned unchanged, so a missing value
     * stays missing and a value that sanitizes to nothing is stored as an
     * empty string rather than becoming null.
     */
    public function sanitize(?string $html, RichTextLevelEnum $level): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        // The cap is a character count. Truncating here, before the pre-pass,
        // keeps a multibyte article whole: the library truncates bytes and
        // returns an empty string when the cut splits a UTF-8 codepoint.
        $html = mb_substr($html, 0, (int) config('rich-text.input_cap'), 'UTF-8');

        return $this->sanitizerFor($level)->sanitize($this->prePass($html, $level));
    }

    private function sanitizerFor(RichTextLevelEnum $level): HtmlSanitizer
    {
        return $this->sanitizers[$level->value] ??= $this->buildSanitizer($level);
    }

    private function buildSanitizer(RichTextLevelEnum $level): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig())
            // Anything not allowed is unwrapped, not dropped, so an unknown wrapper keeps its text.
            ->defaultAction(HtmlSanitizerAction::Block)
            // The service already capped characters, so this only has to be
            // wide enough for the widest UTF-8 encoding (4 bytes per
            // character) and never re-truncates the pre-pass output.
            ->withMaxInputLength(4 * (int) config('rich-text.input_cap'))
            ->allowLinkSchemes(config('rich-text.schemes.links'))
            ->allowMediaSchemes(config('rich-text.schemes.media'))
            ->allowRelativeLinks((bool) config('rich-text.allow_relative_urls'))
            ->allowRelativeMedias((bool) config('rich-text.allow_relative_urls'))
            ->withAttributeSanitizer(new InlineStyleAttributeSanitizer(
                config('rich-text.css.properties'),
                config('rich-text.css.elements'),
            ));

        foreach (config("rich-text.levels.{$level->value}") as $element) {
            $config = $config->allowElement($element, config("rich-text.attributes.{$element}", []));
        }

        foreach (config('rich-text.dropped') as $element) {
            $config = $config->dropElement($element);
        }

        foreach (config('rich-text.forced_attributes') as $element => $attributes) {
            foreach ($attributes as $attribute => $value) {
                $config = $config->forceAttribute($element, $attribute, $value);
            }
        }

        return new HtmlSanitizer($config);
    }

    /**
     * Rewrites the constructs the library pass cannot express as an allowlist:
     * class-matched editor output, embeds, downgraded headings and, for the
     * `inline` level, block boundaries.
     */
    private function prePass(string $html, RichTextLevelEnum $level): string
    {
        $document = @\Dom\HTMLDocument::createFromString(sprintf('<!DOCTYPE html><body>%s</body>', $html));
        $body     = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof \Dom\Element) {
            return $html;
        }

        $this->dropSubtrees($body);
        $this->dropClassMatchedSubtrees($body);
        $this->rewriteEmbeds($body);
        $this->downgradeElements($body);

        if ($level === RichTextLevelEnum::INLINE) {
            $this->flattenBlockBoundaries($body);
            $this->collapseBreaks($body);
            $this->trimTrailingBreaks($body);
        }

        return $body->innerHTML;
    }

    /**
     * Removes the dropped elements together with their contents.
     *
     * Runs in the pre-pass because the library keeps `<head>`-only elements
     * (`style`, `link`, `meta`, `base`) out of its body-context drop list.
     */
    private function dropSubtrees(\Dom\Element $root): void
    {
        /** @var list<string> $dropped */
        $dropped = config('rich-text.dropped');

        foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
            if (in_array(mb_strtolower($element->tagName), $dropped, true)) {
                $element->remove();
            }
        }
    }

    /**
     * Removes the raw-HTML-embed and page-break constructs together with their contents.
     */
    private function dropClassMatchedSubtrees(\Dom\Element $root): void
    {
        /** @var list<string> $dropped */
        $dropped = array_map(strtolower(...), config('rich-text.dropped_classes'));

        foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
            $classes = preg_split('/\s+/', mb_strtolower(mb_trim($element->getAttribute('class') ?? '')), -1, PREG_SPLIT_NO_EMPTY);

            foreach ($classes ?: [] as $class) {
                if (in_array($class, $dropped, true)) {
                    $element->remove();

                    break;
                }
            }
        }
    }

    /**
     * Rewrites a media embed to a link, so its reference survives.
     */
    private function rewriteEmbeds(\Dom\Element $root): void
    {
        /** @var list<string> $embeds */
        $embeds = array_map(strtolower(...), config('rich-text.embed_elements'));

        foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
            if (! in_array(mb_strtolower($element->tagName), $embeds, true)) {
                continue;
            }

            $url = mb_trim($element->getAttribute('url') ?? '');

            if ($url === '') {
                $element->remove();

                continue;
            }

            $link = $element->ownerDocument->createElement('a');
            $link->setAttribute('href', $url);
            $link->textContent = $url;
            $element->replaceWith($link);
        }
    }

    /**
     * Rewrites a disallowed heading (h1) to the nearest allowed one (h2).
     */
    private function downgradeElements(\Dom\Element $root): void
    {
        foreach (config('rich-text.downgrade_elements') as $from => $to) {
            foreach (iterator_to_array($root->getElementsByTagName($from)) as $element) {
                $replacement = $element->ownerDocument->createElement($to);

                foreach (iterator_to_array($element->attributes) as $attribute) {
                    $replacement->setAttribute($attribute->name, $attribute->value);
                }

                while (null !== $child = $element->firstChild) {
                    $replacement->appendChild($child);
                }

                $element->replaceWith($replacement);
            }
        }
    }

    /**
     * Rewrites every block boundary to `<br>` and unwraps the block, so text in
     * adjacent blocks is not glued together when an inline value is spliced
     * into an element the caller already opened.
     */
    private function flattenBlockBoundaries(\Dom\Element $container): void
    {
        /** @var list<string> $boundaries */
        $boundaries = config('rich-text.block_boundaries');

        for ($node = $container->firstChild; $node !== null;) {
            if (! $node instanceof \Dom\Element) {
                $node = $node->nextSibling;

                continue;
            }

            if (! in_array(mb_strtolower($node->tagName), $boundaries, true)) {
                $this->flattenBlockBoundaries($node);
                $node = $node->nextSibling;

                continue;
            }

            $this->flattenBlockBoundaries($node);

            while (null !== $child = $node->firstChild) {
                $node->parentNode->insertBefore($child, $node);
            }

            $node->parentNode->insertBefore($container->ownerDocument->createElement('br'), $node);

            $next = $node->nextSibling;
            $node->remove();
            $node = $next;
        }
    }

    /**
     * Collapses runs of `<br>` that a flattened block produced.
     */
    private function collapseBreaks(\Dom\Element $container): void
    {
        foreach (iterator_to_array($container->getElementsByTagName('br')) as $break) {
            for ($node = $break->nextSibling; $node !== null;) {
                if ($node instanceof \Dom\Element && mb_strtolower($node->tagName) === 'br') {
                    $next = $node->nextSibling;
                    $node->remove();
                    $node = $next;

                    continue;
                }

                if ($node instanceof \Dom\Text && mb_trim($node->textContent) === '') {
                    $next  = $node->nextSibling;
                    $ahead = $next;

                    while ($ahead instanceof \Dom\Text && mb_trim($ahead->textContent) === '') {
                        $ahead = $ahead->nextSibling;
                    }

                    if ($ahead instanceof \Dom\Element && mb_strtolower($ahead->tagName) === 'br') {
                        $node->remove();
                        $node = $next;

                        continue;
                    }
                }

                break;
            }
        }
    }

    /**
     * Trims a trailing `<br>` (and the whitespace a flattened block left behind).
     *
     * Flattening can leave the break inside the last inline element —
     * `<a><p>x</p></a>` becomes `<a>x<br></a>` — so the trim descends through
     * the last-child chain to the deepest end of the value. A break that
     * separates two values is never touched, because only a break with nothing
     * after it is removed.
     */
    private function trimTrailingBreaks(\Dom\Element $container): void
    {
        while ($this->trimTrailingBreakIn($this->deepestTrailingElement($container))) {
        }
    }

    /**
     * The deepest element that ends the value: follow the last non-blank child
     * while it is an element, stopping at the `<br>` the trim removes.
     */
    private function deepestTrailingElement(\Dom\Element $container): \Dom\Element
    {
        $element = $container;

        while (true) {
            $last = $this->lastNonBlankChild($element);

            if (! $last instanceof \Dom\Element || mb_strtolower($last->tagName) === 'br') {
                return $element;
            }

            $element = $last;
        }
    }

    private function lastNonBlankChild(\Dom\Element $element): ?\Dom\Node
    {
        $node = $element->lastChild;

        while ($node instanceof \Dom\Text && mb_trim($node->textContent) === '') {
            $node = $node->previousSibling;
        }

        return $node;
    }

    /**
     * Removes the whitespace-only text that follows a trailing `<br>`, or the
     * trailing `<br>` itself. Returns whether anything was removed.
     */
    private function trimTrailingBreakIn(\Dom\Element $element): bool
    {
        $last = $element->lastChild;

        if ($last instanceof \Dom\Element && mb_strtolower($last->tagName) === 'br') {
            $last->remove();

            return true;
        }

        if (! $last instanceof \Dom\Text || mb_trim($last->textContent) !== '') {
            return false;
        }

        $previous = $last->previousSibling;

        if (! $previous instanceof \Dom\Element || mb_strtolower($previous->tagName) !== 'br') {
            return false;
        }

        $last->remove();

        return true;
    }
}

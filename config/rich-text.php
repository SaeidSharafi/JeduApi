<?php

declare(strict_types=1);

use App\Enums\Content\RichTextLevelEnum;

/*
|--------------------------------------------------------------------------
| Rich text sanitization policy
|--------------------------------------------------------------------------
|
| This file is the whole policy for HTML written through the API. The
| sanitizer (`App\Services\RichText\RichTextSanitizer`) reads it and nothing
| else, so changing the policy is a change here, never a code change.
|
| Sanitization happens once, on the write path, through the cast declared on
| the write DTO property (`#[WithCast(SanitizeRichText::class, …)]`). Values
| read back are served byte for byte; they are never re-sanitized.
|
*/

// The three levels are cumulative: compact contains inline, article contains compact.
$inline  = ['strong', 'b', 'em', 'i', 'u', 's', 'code', 'sub', 'sup', 'mark', 'span', 'a', 'br'];
$compact = [...$inline, 'p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote'];
$article = [...$compact, 'figure', 'figcaption', 'img', 'table', 'thead', 'tbody', 'tfoot', 'tr',
    'th', 'td', 'caption', 'colgroup', 'col', 'pre', 'hr'];

return [

    /*
    |--------------------------------------------------------------------------
    | Input cap
    |--------------------------------------------------------------------------
    |
    | Maximum number of input bytes handed to the sanitizer. Set explicitly so
    | a long article is never silently halved by the library default (20,000).
    | Longer inputs are truncated at this cap before any other rule runs.
    |
    */
    'input_cap' => 500_000,

    /*
    |--------------------------------------------------------------------------
    | Tags per level
    |--------------------------------------------------------------------------
    |
    | A rich field is sanitized against exactly one level, chosen at the field
    | it governs. `inline` has no block tags on purpose: its value is spliced
    | into an element the caller already opened.
    |
    */
    'levels' => [
        RichTextLevelEnum::INLINE->value  => $inline,
        RichTextLevelEnum::COMPACT->value => $compact,
        RichTextLevelEnum::ARTICLE->value => $article,
    ],

    /*
    |--------------------------------------------------------------------------
    | Attributes per element
    |--------------------------------------------------------------------------
    |
    | An element missing from this map keeps no attributes at all. `class` and
    | `data-*` are therefore stripped everywhere, and `id` survives only on
    | `a` (bookmark anchors). `style` is listed only on the elements whose CSS
    | properties are allowed in the `css` section below.
    |
    */
    'attributes' => [
        'a'     => ['href', 'title', 'id', 'rel', 'target'],
        'img'   => ['src', 'alt', 'width', 'height', 'style'],
        'td'    => ['colspan', 'rowspan', 'style'],
        'th'    => ['colspan', 'rowspan', 'style'],
        'ol'    => ['start', 'reversed', 'type'],
        'span'  => ['lang', 'dir'],
        'col'   => ['span', 'width', 'style'],
        'p'     => ['style'],
        'h2'    => ['style'],
        'h3'    => ['style'],
        'h4'    => ['style'],
        'table' => ['style'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inline CSS
    |--------------------------------------------------------------------------
    |
    | Only these properties survive `style`, and only on the listed elements.
    | Typography, colour, spacing and positioning are dropped so pasted Word /
    | Google Docs content inherits the shop's own styles. The filter runs as a
    | Symfony attribute sanitizer for `style`, not as post-processing.
    |
    */
    'css' => [
        'properties' => ['text-align', 'width', 'height', 'aspect-ratio', 'vertical-align', 'border-collapse'],
        'elements'   => [
            'text-align'      => ['p', 'h2', 'h3', 'h4', 'td', 'th'],
            'width'           => ['img', 'col', 'table'],
            'height'          => ['img', 'col', 'table'],
            'aspect-ratio'    => ['img', 'col', 'table'],
            'vertical-align'  => ['td', 'th'],
            'border-collapse' => ['table'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | URL schemes
    |--------------------------------------------------------------------------
    |
    | Link schemes are limited to http, https, mailto and tel; media sources to
    | http and https, so `javascript:` and `data:` payloads cannot be stored.
    | Relative URLs (a path or a fragment with no scheme) are allowed because
    | they cannot smuggle a scheme and the editor's uploads and internal links
    | are written that way; they are still scheme-checked when parsed.
    |
    */
    'schemes' => [
        'links' => ['http', 'https', 'mailto', 'tel'],
        'media' => ['http', 'https'],
    ],

    'allow_relative_urls' => true,

    /*
    |--------------------------------------------------------------------------
    | Removed with their subtree
    |--------------------------------------------------------------------------
    |
    | Everything not listed in `levels` or here is unwrapped, not dropped, so
    | an unknown wrapper keeps its text content. These elements are removed
    | together with their children: nothing executable or content-smuggling
    | can be stored.
    |
    */
    'dropped' => [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta',
        'base', 'svg', 'math', 'template', 'noscript',
    ],

    /*
    |--------------------------------------------------------------------------
    | Removed with their subtree, matched by class
    |--------------------------------------------------------------------------
    |
    | The two editor plugins that bypass normal editing: HTML Embed renders a
    | `raw-html-embed` wrapper holding arbitrary markup, and the Page Break
    | plugin renders a `page-break` wrapper. Both are stripped with their
    | contents in the pre-pass, before the tag filter runs.
    |
    */
    'dropped_classes' => ['raw-html-embed', 'page-break'],

    /*
    |--------------------------------------------------------------------------
    | Forced attributes
    |--------------------------------------------------------------------------
    |
    | Written over whatever the input carried, so a link that opens in a new
    | tab can never take over the shop's tab.
    |
    */
    'forced_attributes' => [
        'a' => ['rel' => 'noopener noreferrer'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pre-pass rewrites
    |--------------------------------------------------------------------------
    |
    | `h1` is never allowed at any level: it is downgraded to `h2` so a pasted
    | document keeps its heading structure without competing with the page
    | title. A media `<oembed>` embed is rewritten to a link, so the reference
    | survives when the storefront has no embed renderer.
    |
    */
    'downgrade_elements' => ['h1' => 'h2'],

    'embed_elements' => ['oembed'],

    /*
    |--------------------------------------------------------------------------
    | Block boundaries
    |--------------------------------------------------------------------------
    |
    | Used only when flattening an `inline` value: the boundary of each of
    | these elements is rewritten to `<br>` before the tag filter runs, and a
    | trailing `<br>` is trimmed, so `<p>a</p><p>b</p>` becomes `a<br>b` rather
    | than the two paragraphs being glued into `ab`.
    |
    */
    'block_boundaries' => [
        'address', 'article', 'aside', 'blockquote', 'caption', 'dd', 'div', 'dl', 'dt',
        'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header',
        'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'tbody', 'td',
        'tfoot', 'th', 'thead', 'tr', 'ul',
    ],

];

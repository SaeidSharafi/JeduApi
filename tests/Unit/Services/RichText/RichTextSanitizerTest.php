<?php

declare(strict_types=1);

use App\Enums\Content\RichTextLevelEnum;
use App\Services\RichText\RichTextSanitizer;

mutates(RichTextSanitizer::class);

beforeEach(function (): void {
    $this->sanitizer = new RichTextSanitizer();
});

describe('removing hostile markup', function (): void {
    it('removes the element and its subtree', function (string $html): void {
        expect($this->sanitizer->sanitize($html, RichTextLevelEnum::ARTICLE))->toBe('');
    })->with([
        'script'     => '<script>alert(1)</script>',
        'style'      => '<style>body{display:none}</style>',
        'iframe'     => '<iframe src="https://evil.test">payload</iframe>',
        'object'     => '<object data="https://evil.test/x">payload</object>',
        'embed'      => '<embed src="https://evil.test/x">',
        'form'       => '<form action="/steal"><input name="a">payload</form>',
        'link'       => '<link rel="stylesheet" href="https://evil.test/x.css">',
        'meta'       => '<meta http-equiv="refresh" content="0;url=https://evil.test">',
        'base'       => '<base href="https://evil.test">',
        'svg'        => '<svg><circle r="1">payload</circle></svg>',
        'math'       => '<math><mi>payload</mi></math>',
        'template'   => '<template><p>payload</p></template>',
        'noscript'   => '<noscript>payload</noscript>',
        'html embed' => '<div class="raw-html-embed"><p>payload</p></div>',
        'page break' => '<div class="page-break" style="page-break-after: always;"><span>payload</span></div>',
    ]);

    it('strips event handlers', function (): void {
        expect($this->sanitizer->sanitize('<p onclick="alert(1)" onmouseover="alert(2)">x</p>', RichTextLevelEnum::ARTICLE))
            ->toBe('<p>x</p>');
    });

    it('drops unsafe URL schemes', function (string $html, string $expected): void {
        expect($this->sanitizer->sanitize($html, RichTextLevelEnum::ARTICLE))->toBe($expected);
    })->with([
        'javascript href'       => ['<a href="javascript:alert(1)">x</a>', '<a rel="noopener noreferrer">x</a>'],
        'entity-encoded scheme' => ['<a href="java&#115;cript:alert(1)">x</a>', '<a rel="noopener noreferrer">x</a>'],
        'data href'             => ['<a href="data:text/html,<script>alert(1)</script>">x</a>', '<a rel="noopener noreferrer">x</a>'],
        'data image source'     => ['<img src="data:image/svg+xml;base64,PHN2Zz4=" alt="x">', '<img alt="x" />'],
        'vbscript image source' => ['<img src="vbscript:alert(1)">', '<img />'],
        'mixed-case scheme'     => ['<a href="JaVaScRiPt:alert(1)">x</a>', '<a rel="noopener noreferrer">x</a>'],
    ]);

    it('removes a dropped element nested in kept content and keeps the text around it', function (): void {
        expect($this->sanitizer->sanitize('<p>a<script>alert(1)</script>b</p>', RichTextLevelEnum::ARTICLE))
            ->toBe('<p>ab</p>');
    });

    it('strips element identity so the policy cannot be bypassed through it', function (): void {
        expect($this->sanitizer->sanitize('<p class="x" data-embed="1" id="y" dir="rtl">t</p>', RichTextLevelEnum::ARTICLE))
            ->toBe('<p>t</p>');
    });
});

describe('keeping allowed constructs', function (): void {
    it('downgrades h1 to h2 instead of dropping it', function (): void {
        expect($this->sanitizer->sanitize('<h1>عنوان</h1>', RichTextLevelEnum::ARTICLE))
            ->toBe('<h2>عنوان</h2>');
    });

    it('keeps inline formatting, lists, quotes and highlights', function (string $html): void {
        expect($this->sanitizer->sanitize($html, RichTextLevelEnum::ARTICLE))->toBe($html);
    })->with([
        'inline marks' => ['<p><strong>a</strong> <b>b</b> <em>c</em> <i>d</i> <u>e</u> <s>f</s> <code>g</code> <sub>h</sub> <sup>i</sup> <mark>j</mark></p>'],
        'list'         => ['<ul><li>one</li><li>two</li></ul>'],
        'ordered list' => ['<ol start="3"><li>three</li></ol>'],
        'quote'        => ['<blockquote><p>quoted</p></blockquote>'],
        'preformatted' => ['<pre><code>code block</code></pre>'],
        'rule'         => ['<hr />'],
    ]);

    it('keeps the mixed-language span attributes', function (): void {
        expect($this->sanitizer->sanitize('<span lang="fa" dir="rtl">متن</span>', RichTextLevelEnum::ARTICLE))
            ->toBe('<span lang="fa" dir="rtl">متن</span>');
    });

    it('keeps a figure with an explicitly sized image and its caption', function (): void {
        expect($this->sanitizer->sanitize(
            '<figure><img src="https://cdn.test/a.png" alt="a" width="640" height="480"><figcaption>cap</figcaption></figure>',
            RichTextLevelEnum::ARTICLE,
        ))->toBe('<figure><img src="https://cdn.test/a.png" alt="a" width="640" height="480" /><figcaption>cap</figcaption></figure>');
    });

    it('keeps a table with column widths and cell spans', function (): void {
        expect($this->sanitizer->sanitize(
            '<table><colgroup><col width="120"><col width="240"></colgroup><tbody><tr><td colspan="2">a</td></tr></tbody></table>',
            RichTextLevelEnum::ARTICLE,
        ))->toBe('<table><colgroup><col width="120" /><col width="240" /></colgroup><tbody><tr><td colspan="2">a</td></tr></tbody></table>');
    });

    it('forces rel noopener noreferrer while keeping the link target and schemes', function (string $html, string $expected): void {
        expect($this->sanitizer->sanitize($html, RichTextLevelEnum::ARTICLE))->toBe($expected);
    })->with([
        'new tab'     => ['<a href="https://example.test/x" target="_blank">x</a>', '<a href="https://example.test/x" target="_blank" rel="noopener noreferrer">x</a>'],
        'bookmark id' => ['<a id="section-2">x</a>', '<a id="section-2" rel="noopener noreferrer">x</a>'],
        'mailto'      => ['<a href="mailto:a@example.test">x</a>', '<a href="mailto:a&#64;example.test" rel="noopener noreferrer">x</a>'],
        'tel'         => ['<a href="tel:+15551234">x</a>', '<a href="tel:&#43;15551234" rel="noopener noreferrer">x</a>'],
        'relative'    => ['<a href="/courses/laravel">x</a>', '<a href="/courses/laravel" rel="noopener noreferrer">x</a>'],
    ]);

    it('keeps a relatively addressed image', function (): void {
        expect($this->sanitizer->sanitize('<img src="/storage/a.png" alt="a">', RichTextLevelEnum::ARTICLE))
            ->toBe('<img src="/storage/a.png" alt="a" />');
    });

    it('unwraps an unknown element and keeps its text', function (): void {
        expect($this->sanitizer->sanitize('<section><custom-tag>kept</custom-tag></section>', RichTextLevelEnum::ARTICLE))
            ->toBe('kept');
    });
});

describe('filtering inline CSS', function (): void {
    it('drops pasted typography, colour, spacing and positioning', function (): void {
        expect($this->sanitizer->sanitize(
            '<p style="font-family: Arial; font-size: 12px; font-weight: bold; color: red; background-color: #fff; margin: 0; padding: 2px; line-height: 2; letter-spacing: 1px; position: fixed; display: none; float: left; page-break-after: always; text-align: center">x</p>',
            RichTextLevelEnum::ARTICLE,
        ))->toBe('<p style="text-align: center">x</p>');
    });

    it('keeps alignment and image sizing on the elements that may carry them', function (string $html, string $expected): void {
        expect($this->sanitizer->sanitize($html, RichTextLevelEnum::ARTICLE))->toBe($expected);
    })->with([
        'heading alignment' => ['<h2 style="text-align: right">x</h2>', '<h2 style="text-align: right">x</h2>'],
        'cell alignment'    => ['<table><tbody><tr><td style="text-align: center; vertical-align: middle">x</td></tr></tbody></table>', '<table><tbody><tr><td style="text-align: center; vertical-align: middle">x</td></tr></tbody></table>'],
        'table collapse'    => ['<table style="border-collapse: collapse; width: 100%"><tbody><tr><td>x</td></tr></tbody></table>', '<table style="border-collapse: collapse; width: 100%"><tbody><tr><td>x</td></tr></tbody></table>'],
        'image sizing'      => ['<img src="https://cdn.test/a.png" style="width: 300px; height: auto; aspect-ratio: 16 / 9">', '<img src="https://cdn.test/a.png" style="width: 300px; height: auto; aspect-ratio: 16 / 9" />'],
    ]);

    it('drops a property on an element it is not allowed on', function (string $html, string $expected): void {
        expect($this->sanitizer->sanitize($html, RichTextLevelEnum::ARTICLE))->toBe($expected);
    })->with([
        'width on a paragraph'  => ['<p style="width: 400px">x</p>', '<p>x</p>'],
        'alignment on an image' => ['<img src="https://cdn.test/a.png" style="text-align: center">', '<img src="https://cdn.test/a.png" />'],
    ]);

    it('drops the style attribute where no property is allowed', function (): void {
        expect($this->sanitizer->sanitize('<span style="font-weight: bold">x</span>', RichTextLevelEnum::ARTICLE))
            ->toBe('<span>x</span>');
    });
});

describe('assigning a level', function (): void {
    it('sanitizes against the tags the field is declared at', function (string $html, RichTextLevelEnum $level, string $expected): void {
        expect($this->sanitizer->sanitize($html, $level))->toBe($expected);
    })->with([
        'inline allows marks'       => ['<strong>x</strong>', RichTextLevelEnum::INLINE, '<strong>x</strong>'],
        'inline rejects images'     => ['<img src="https://cdn.test/a.png" alt="a">', RichTextLevelEnum::INLINE, ''],
        'inline rejects headings'   => ['<h2>x</h2>', RichTextLevelEnum::INLINE, 'x'],
        'compact allows paragraphs' => ['<p>x</p>', RichTextLevelEnum::COMPACT, '<p>x</p>'],
        'compact keeps headings'    => ['<h3>x</h3>', RichTextLevelEnum::COMPACT, '<h3>x</h3>'],
        'compact rejects images'    => ['<img src="https://cdn.test/a.png" alt="a">', RichTextLevelEnum::COMPACT, ''],
        'compact unwraps tables'    => ['<table><tbody><tr><td>x</td></tr></tbody></table>', RichTextLevelEnum::COMPACT, 'x'],
        'article keeps tables'      => ['<table><tbody><tr><td>x</td></tr></tbody></table>', RichTextLevelEnum::ARTICLE, '<table><tbody><tr><td>x</td></tr></tbody></table>'],
        'article keeps figures'     => ['<figure><figcaption>x</figcaption></figure>', RichTextLevelEnum::ARTICLE, '<figure><figcaption>x</figcaption></figure>'],
    ]);
});

describe('flattening an inline value', function (): void {
    it('rewrites block boundaries to line breaks instead of gluing text', function (string $html, string $expected): void {
        expect($this->sanitizer->sanitize($html, RichTextLevelEnum::INLINE))->toBe($expected);
    })->with([
        'adjacent paragraphs' => ['<p>a</p><p>b</p>', 'a<br />b'],
        'single paragraph'    => ['<p>a</p>', 'a'],
        'divs'                => ['<div>a</div><div>b</div>', 'a<br />b'],
        'nested blocks'       => ['<blockquote><p>q</p><p>r</p></blockquote>', 'q<br />r'],
        'list items'          => ['<ul><li>a</li><li>b</li></ul>', 'a<br />b'],
        'heading'             => ['<h2>x</h2>', 'x'],
        'paragraph and text'  => ['<p>a</p>tail', 'a<br />tail'],
        'existing breaks'     => ['<p>line1<br>line2</p>', 'line1<br />line2'],
        'empty paragraph'     => ['<p>a</p><p></p><p>b</p>', 'a<br />b'],
    ]);

    it('keeps block structure at the compact and article levels', function (RichTextLevelEnum $level): void {
        expect($this->sanitizer->sanitize('<p>a</p><p>b</p>', $level))->toBe('<p>a</p><p>b</p>');
    })->with([RichTextLevelEnum::COMPACT, RichTextLevelEnum::ARTICLE]);
});

describe('rewriting a media embed', function (): void {
    it('turns an oembed element into a link', function (string $html, RichTextLevelEnum $level, string $expected): void {
        expect($this->sanitizer->sanitize($html, $level))->toBe($expected);
    })->with([
        'in a figure' => [
            '<figure class="media"><oembed url="https://www.youtube.com/embed/abc"></oembed></figure>',
            RichTextLevelEnum::ARTICLE,
            '<figure><a href="https://www.youtube.com/embed/abc" rel="noopener noreferrer">https://www.youtube.com/embed/abc</a></figure>',
        ],
        'inline' => [
            '<oembed url="https://www.youtube.com/embed/abc"></oembed>',
            RichTextLevelEnum::INLINE,
            '<a href="https://www.youtube.com/embed/abc" rel="noopener noreferrer">https://www.youtube.com/embed/abc</a>',
        ],
        'without a url' => ['<p><oembed></oembed></p>', RichTextLevelEnum::ARTICLE, '<p></p>'],
    ]);
});

describe('bounding the input', function (): void {
    it('truncates input at the configured cap', function (): void {
        $sanitized = $this->sanitizer->sanitize(str_repeat('a', 600_000), RichTextLevelEnum::ARTICLE);

        expect($sanitized)->toHaveLength(500_000);
    });

    it('keeps an article longer than the library default', function (): void {
        $sanitized = $this->sanitizer->sanitize('<p>'.str_repeat('a', 30_000).'</p>', RichTextLevelEnum::ARTICLE);

        expect($sanitized)->toHaveLength(30_007);
    });
});

describe('sanitizing twice', function (): void {
    it('returns identical bytes for an already sanitized value', function (string $html, RichTextLevelEnum $level): void {
        $once = $this->sanitizer->sanitize($html, $level);

        expect($this->sanitizer->sanitize($once, $level))->toBe($once);
    })->with([
        'article' => [
            '<h1 id="t" onclick="x">عنوان</h1><p class="lead" style="font-family: Arial; text-align: center">متن <mark>مهم</mark> <a href="https://example.test/a?b=1" target="_blank">لینک</a></p>'
            .'<figure><img src="https://cdn.test/a.png" alt="a" style="width: 300px"><figcaption>cap</figcaption></figure>'
            .'<table style="border-collapse: collapse"><colgroup><col width="120"></colgroup><tbody><tr><td colspan="2" style="vertical-align: top">x</td></tr></tbody></table>'
            .'<script>alert(1)</script><div class="raw-html-embed"><p>gone</p></div>',
            RichTextLevelEnum::ARTICLE,
        ],
        'compact' => ['<h2>x</h2><p>a<br>b</p><ul><li>c</li></ul>', RichTextLevelEnum::COMPACT],
        'inline'  => ['<p>a</p><p>b <em>c</em></p>', RichTextLevelEnum::INLINE],
    ]);
});

describe('passing values through', function (): void {
    it('returns null for null', function (): void {
        expect($this->sanitizer->sanitize(null, RichTextLevelEnum::ARTICLE))->toBeNull();
    });

    it('returns an empty string for empty and whitespace-only input', function (string $input): void {
        expect($this->sanitizer->sanitize($input, RichTextLevelEnum::ARTICLE))->toBe('');
    })->with(['empty' => '', 'whitespace' => '   ']);

    it('keeps plain text unchanged', function (): void {
        expect($this->sanitizer->sanitize('Plain text.', RichTextLevelEnum::ARTICLE))->toBe('Plain text.');
    });

    it('preserves Persian text and the zero-width non-joiner', function (): void {
        $zwnj = "\u{200C}";
        $html = "<p>می{$zwnj}روم کتاب می{$zwnj}خوانم</p>";

        expect($this->sanitizer->sanitize($html, RichTextLevelEnum::ARTICLE))->toBe($html);
    });
});

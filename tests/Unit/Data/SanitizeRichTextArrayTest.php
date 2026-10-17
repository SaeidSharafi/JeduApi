<?php

declare(strict_types=1);

use App\Data\Casts\SanitizeRichTextArray;
use App\Enums\Content\RichTextLevelEnum;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

mutates(SanitizeRichTextArray::class);

beforeEach(function (): void {
    $this->property = Mockery::mock(DataProperty::class);
    $this->context  = Mockery::mock(CreationContext::class);
});

it('sanitizes the answer of every element and leaves the other keys byte-identical', function (): void {
    $caster = new SanitizeRichTextArray('answer', RichTextLevelEnum::COMPACT);

    $faqs = [
        [
            'question'   => 'What is <em class="editor-mark">covered</em>?',
            'answer'     => '<p>Safe</p><script>alert(1)</script>',
            'is_visible' => true,
        ],
        [
            'question'   => 'Sibling question',
            'answer'     => 'Plain answer',
            'is_visible' => false,
        ],
    ];

    expect($caster->cast($this->property, $faqs, [], $this->context))->toBe([
        [
            'question'   => 'What is <em class="editor-mark">covered</em>?',
            'answer'     => '<p>Safe</p>',
            'is_visible' => true,
        ],
        [
            'question'   => 'Sibling question',
            'answer'     => 'Plain answer',
            'is_visible' => false,
        ],
    ]);
});

it('passes an empty answer and an empty array through unchanged', function (): void {
    $caster = new SanitizeRichTextArray('answer', RichTextLevelEnum::COMPACT);

    expect($caster->cast($this->property, [['question' => 'q', 'answer' => '']], [], $this->context))
        ->toBe([['question' => 'q', 'answer' => '']])
        ->and($caster->cast($this->property, [], [], $this->context))
        ->toBe([]);
});

it('passes malformed elements through unchanged', function (mixed $element): void {
    $caster = new SanitizeRichTextArray('answer', RichTextLevelEnum::COMPACT);

    expect($caster->cast($this->property, [$element], [], $this->context))->toBe([$element]);
})->with([
    'non-array element' => ['not-an-array'],
    'missing key'       => [['question' => 'no answer here']],
    'null value'        => [['answer' => null]],
    'integer value'     => [['answer' => 123]],
    'array value'       => [['answer' => ['nested' => 'value']]],
]);

it('sanitizes the html key at the inline level and leaves the other certification keys untouched', function (): void {
    $caster = new SanitizeRichTextArray('html', RichTextLevelEnum::INLINE);

    $certifications = [
        [
            'name'  => 'Enamad',
            'image' => 5,
            'html'  => '<p>First</p><p>Second</p><script>alert(1)</script>',
        ],
    ];

    expect($caster->cast($this->property, $certifications, [], $this->context))->toBe([
        [
            'name'  => 'Enamad',
            'image' => 5,
            'html'  => 'First<br />Second',
        ],
    ]);
});

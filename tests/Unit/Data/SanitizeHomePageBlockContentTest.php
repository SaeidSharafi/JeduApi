<?php

declare(strict_types=1);

use App\Data\Casts\SanitizeHomePageBlockContent;
use App\Enums\Content\HomePageBlockTypeEnum;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

mutates(SanitizeHomePageBlockContent::class);

/**
 * Runs the cast with the given sibling properties.
 *
 * @param  array<string, mixed>  $properties
 * @param  array<string, mixed>  $content
 * @return array<string, mixed>
 */
function castHomePageBlockContent(array $properties, array $content): array
{
    return (new SanitizeHomePageBlockContent())->cast(
        Mockery::mock(DataProperty::class),
        $content,
        $properties,
        Mockery::mock(CreationContext::class),
    );
}

it('sanitizes a banner block content at the inline level', function (): void {
    $content = [
        'image_id'     => 7,
        'image_url'    => 'https://cdn.test/banner.jpg',
        'action'       => 'go_to_shop',
        'action_title' => 'Shop Now',
        'content'      => '<p>a</p><p>b</p>',
        'preset'       => 'default',
    ];

    $result = castHomePageBlockContent(['type' => HomePageBlockTypeEnum::BANNER], $content);

    expect($result)->toBe([...$content, 'content' => 'a<br />b']);
});

it('sanitizes a webinar banner text at the inline level', function (): void {
    $content = [
        'product_id' => 3,
        'text'       => '<p>a</p><p>b</p>',
        'image_id'   => 7,
        'image_url'  => 'https://cdn.test/banner.jpg',
    ];

    $result = castHomePageBlockContent(['type' => 'WEBINAR_BANNER'], $content);

    expect($result)->toBe([...$content, 'text' => 'a<br />b']);
});

it('chooses the rich key from the declared type, never from the keys present', function (array $properties, array $content, array $expected): void {
    expect(castHomePageBlockContent($properties, $content))->toBe($expected);
})->with([
    'banner sanitizes content and leaves a text key alone' => [
        ['type' => HomePageBlockTypeEnum::BANNER],
        ['content' => '<p>a</p><p>b</p>', 'text' => '<p>c</p><p>d</p>'],
        ['content' => 'a<br />b', 'text' => '<p>c</p><p>d</p>'],
    ],
    'webinar banner sanitizes text and leaves a content key alone' => [
        ['type' => 'WEBINAR_BANNER'],
        ['text' => '<p>a</p><p>b</p>', 'content' => '<p>c</p><p>d</p>'],
        ['text' => 'a<br />b', 'content' => '<p>c</p><p>d</p>'],
    ],
    'banner without a content key keeps its text key untouched' => [
        ['type' => HomePageBlockTypeEnum::BANNER],
        ['text' => '<p>c</p><p>d</p>'],
        ['text' => '<p>c</p><p>d</p>'],
    ],
    'webinar banner without a text key keeps its content key untouched' => [
        ['type' => HomePageBlockTypeEnum::WEBINAR_BANNER],
        ['content' => '<p>c</p><p>d</p>'],
        ['content' => '<p>c</p><p>d</p>'],
    ],
    'a null rich value passes through' => [
        ['type' => HomePageBlockTypeEnum::BANNER],
        ['content' => null, 'action' => 'go_to_shop'],
        ['content' => null, 'action' => 'go_to_shop'],
    ],
]);

it('leaves a block type with no rich field untouched', function (string $type, array $content): void {
    expect(castHomePageBlockContent(['type' => $type], $content))->toBe($content);
})->with([
    'main categories' => [
        'MAIN_CATEGORIES',
        ['items' => [1, 2, 3], 'preset' => 'default', 'content' => '<p>a</p><p>b</p>'],
    ],
    'curated list' => [
        'CURATED_LIST',
        ['items' => [4, 5], 'preset' => 'default', 'content' => '<p>a</p><p>b</p>'],
    ],
    'dynamic list' => [
        'DYNAMIC_LIST',
        ['entity_type' => 'course_products', 'sort_by' => 'created_at:desc', 'limit' => 5, 'preset' => 'grid', 'content' => '<p>a</p><p>b</p>'],
    ],
]);

it('leaves the payload untouched when the declared type is absent or unrecognised', function (array $properties): void {
    $content = ['content' => '<p>a</p><p>b</p>', 'text' => '<p>c</p><p>d</p>'];

    expect(castHomePageBlockContent($properties, $content))->toBe($content);
})->with([
    'type absent'       => [[]],
    'type null'         => [['type' => null]],
    'type unrecognised' => [['type' => 'RAW_HTML']],
]);

it('takes the rich key from the policy file, not from code', function (): void {
    config(['rich-text.home_page_block_keys.BANNER' => 'action_title']);

    $content = ['content' => '<p>a</p><p>b</p>', 'action_title' => '<p>c</p><p>d</p>'];

    expect(castHomePageBlockContent(['type' => HomePageBlockTypeEnum::BANNER], $content))
        ->toBe(['content' => '<p>a</p><p>b</p>', 'action_title' => 'c<br />d']);
});

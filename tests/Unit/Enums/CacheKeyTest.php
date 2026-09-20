<?php

declare(strict_types=1);

use App\Enums\System\CacheKey;
use App\Enums\System\CacheTag;

covers(CacheKey::class, CacheTag::class);

it('substitutes named parameters into the key template', function (): void {
    $resolved = CacheKey::GoodForStart->resolve(['slug' => 'math', 'limit' => 12]);

    expect($resolved)->toBe('shop.category.math.good-for-start.courses-12');
});

it('returns the bare template when no parameters are given', function (): void {
    expect(CacheKey::Slider->resolve())->toBe('shop.homepage.sliders');
});

it('resolves every placeholder declared by a registry key', function (): void {
    foreach (CacheKey::cases() as $key) {
        preg_match_all('/\{(\w+)\}/', $key->value, $matches);

        $resolved = $key->resolve(array_fill_keys($matches[1], 'value'));

        expect($resolved)->not->toContain('{')->not->toContain('}');
    }
});

it('resolves an otp template with its runtime parameters', function (): void {
    $resolved = CacheKey::OtpValue->resolve([
        'identifier' => '09120000000',
        'guard'      => 'web',
        'type'       => 'login',
    ]);

    expect($resolved)->toBe('otp_09120000000_web_value_login');
});

it('refuses to resolve a template when a parameter is missing', function (): void {
    expect(fn (): string => CacheKey::GoodForStart->resolve(['slug' => 'math']))
        ->toThrow(InvalidArgumentException::class);
});

it('exposes the invalidation vocabulary', function (): void {
    expect(CacheTag::getAllValues())->toBe([
        'home_page',
        'content',
        'catalog',
        'search',
        'discounts',
        'settings',
        'auth',
    ]);
});

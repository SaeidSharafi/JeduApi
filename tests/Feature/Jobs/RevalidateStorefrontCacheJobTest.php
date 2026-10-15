<?php

declare(strict_types=1);

use App\Jobs\RevalidateStorefrontCacheJob;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

mutates(RevalidateStorefrontCacheJob::class);

it('sends the tags it carries', function (): void {
    config()->set('storefront.url', 'https://shop.test');
    config()->set('storefront.revalidate_secret', 'shared-secret');
    Http::fake(['shop.test/*' => Http::response(['ok' => true], 200)]);

    (new RevalidateStorefrontCacheJob(['catalog:courses', 'blog:posts']))->handle(
        app(App\Services\Storefront\StorefrontRevalidationService::class),
    );

    Http::assertSent(fn (Request $request): bool => $request->data() === ['tags' => ['catalog:courses', 'blog:posts']]);
});

it('is a no-op when the storefront is not configured', function (): void {
    config()->set('storefront.revalidate_secret', null);

    (new RevalidateStorefrontCacheJob(['catalog:courses']))->handle(
        app(App\Services\Storefront\StorefrontRevalidationService::class),
    );

    Http::assertNothingSent();
});

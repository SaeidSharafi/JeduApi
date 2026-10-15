<?php

declare(strict_types=1);

use App\Console\Commands\Storefront\RevalidateStorefrontCacheCommand;
use App\Enums\System\StorefrontTag;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

mutates(RevalidateStorefrontCacheCommand::class);

beforeEach(function (): void {
    config()->set('storefront.url', 'https://shop.test');
    config()->set('storefront.revalidate_secret', 'shared-secret');
    Http::fake(['shop.test/*' => Http::response(['ok' => true], 200)]);
});

it('revalidates the tags it is given', function (): void {
    $this->artisan('storefront:revalidate', ['--tag' => ['catalog:courses', 'home:blocks']])
        ->expectsOutput('Revalidated 2 storefront tag(s): catalog:courses, home:blocks.')
        ->assertExitCode(0);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://shop.test/api/revalidate'
        && $request->data()                                         === ['tags' => ['catalog:courses', 'home:blocks']]);
});

it('revalidates every tag with --all', function (): void {
    $this->artisan('storefront:revalidate --all')->assertExitCode(0);

    Http::assertSent(fn (Request $request): bool => $request->data() === ['tags' => StorefrontTag::values()]);
});

it('rejects an unknown tag without calling the storefront', function (): void {
    $this->artisan('storefront:revalidate', ['--tag' => ['catalog:nope']])
        ->expectsOutput(sprintf(
            'Unknown storefront tag(s) [catalog:nope]. Available tags: %s.',
            implode(', ', StorefrontTag::values()),
        ))
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('reports that there is nothing to revalidate when given no tag', function (): void {
    $this->artisan('storefront:revalidate')->assertExitCode(1);

    Http::assertNothingSent();
});

it('reports failure when the storefront refuses the payload', function (): void {
    // A distinct host on purpose: Http::fake() merges stubs and the first match wins,
    // so re-faking shop.test/* here would still hit the successful stub from beforeEach.
    config()->set('storefront.url', 'https://refusing.test');
    Http::fake(['refusing.test/*' => Http::response(['error' => 'unknown tags', 'rejected' => ['catalog:courses']], 400)]);

    $this->artisan('storefront:revalidate --all')
        ->expectsOutput('The storefront refused the revalidation; its error and rejected tags are in the log.')
        ->assertExitCode(1);
});

it('refuses to run when the webhook is not configured', function (): void {
    config()->set('storefront.revalidate_secret', null);

    $this->artisan('storefront:revalidate --all')
        ->expectsOutput('The storefront webhook is not configured (STOREFRONT_URL / STOREFRONT_REVALIDATE_SECRET); nothing was sent.')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

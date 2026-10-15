<?php

declare(strict_types=1);

use App\Actions\Admin\Bundle\UpdateBundleAction;
use App\Data\Admin\Bundle\BundleUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Bundle;
use App\Models\Product;

mutates(UpdateBundleAction::class);

/**
 * A sellable bundle: the bundle row plus the Product row that carries its slug,
 * because /bundles/{product:slug} resolves against products.slug.
 *
 * @return array{0: Bundle, 1: Product}
 */
function updatableBundle(): array
{
    $bundle  = Bundle::factory()->create(['slug' => 'starter-pack']);
    $product = Product::factory()->create([
        'productable_type' => ProductableEnum::BUNDLE->value,
        'productable_id'   => $bundle->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'slug'             => 'starter-pack',
    ]);

    return [$bundle, $product];
}

function bundleUpdatePayload(array $overrides = []): BundleUpdateData
{
    return BundleUpdateData::from([
        'full_name'   => 'Starter Pack',
        'short_name'  => 'STARTER',
        'description' => 'Everything a beginner needs.',
        'status'      => PublicationStatusEnum::PUBLISHED->value,
        'media'       => [],
        ...$overrides,
    ]);
}

it('propagates a new slug to the bundle and its products', function (): void {
    [$bundle, $product] = updatableBundle();

    app(UpdateBundleAction::class)->handle(
        bundleUpdatePayload(['slug' => 'beginner-pack']),
        $bundle,
    );

    expect($bundle->fresh()->slug)->toBe('beginner-pack')
        ->and($product->fresh()->slug)->toBe('beginner-pack');
});

it('keeps the current slug on the bundle and its products when the request omits it', function (): void {
    [$bundle, $product] = updatableBundle();

    app(UpdateBundleAction::class)->handle(bundleUpdatePayload(), $bundle);

    expect($bundle->fresh()->slug)->toBe('starter-pack')
        ->and($product->fresh()->slug)->toBe('starter-pack');
});

it('keeps the current slug on the bundle and its products when the request slug is empty', function (): void {
    [$bundle, $product] = updatableBundle();

    app(UpdateBundleAction::class)->handle(
        bundleUpdatePayload(['slug' => '']),
        $bundle,
    );

    expect($bundle->fresh()->slug)->toBe('starter-pack')
        ->and($product->fresh()->slug)->toBe('starter-pack');
});

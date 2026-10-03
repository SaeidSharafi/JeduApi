<?php

declare(strict_types=1);

use App\Actions\Admin\Product\ArchiveProductAction;
use App\Enums\Content\PublicationStatusEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use Illuminate\Support\Facades\Event;

it('archives the product and dispatches all invalidation events', function (): void {
    Event::fake([ProductCacheInvalidated::class, ProductAvailabilityCacheInvalidated::class, ProductSearchIndexInvalidated::class]);
    $product = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    app(ArchiveProductAction::class)->handle($product);

    expect($product->fresh()->status)->toBe(PublicationStatusEnum::ARCHIVED);
    Event::assertDispatched(ProductCacheInvalidated::class, fn ($event): bool => $event->productId === $product->id);
    Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn ($event): bool => $event->productIds === [$product->id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn ($event): bool => $event->productIds === [$product->id]);
});

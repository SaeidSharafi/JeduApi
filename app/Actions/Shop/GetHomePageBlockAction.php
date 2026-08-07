<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Data\Shop\Blog\BlogPostCardData;
use App\Data\Shop\HomePage\BannerData;
use App\Data\Shop\HomePage\HomePageBlockData;
use App\Data\Shop\HomePage\WebinarBannerData;
use App\Data\Shop\Product\Category\CategoryCardData;
use App\Data\Shop\Product\ProductCardData;
use App\Enums\Content\DynamicListEntityTypeEnum;
use App\Enums\Content\DynamicListSortByEnum;
use App\Enums\Content\HomePageBlockTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\HomePageBlock;
use App\Models\Product;
use App\Services\ProductPriceService;
use App\Services\RequestDataCacheService;
use Illuminate\Database\Eloquent\Builder;

final readonly class GetHomePageBlockAction
{
    public function __construct(
        private ProductPriceService $priceService,
        private RequestDataCacheService $requestCache
    ) {}

    public function handle(HomePageBlock $block): HomePageBlockData
    {
        // Pre-load required data for this specific block
        $preloadedData = $this->preloadRequiredDataForBlock($block);

        // Hydrate the block content
        $hydratedContent = match ($block->type) {
            HomePageBlockTypeEnum::MAIN_CATEGORIES => $this->hydrateCuratedList($block, $preloadedData),
            HomePageBlockTypeEnum::CURATED_LIST    => $this->hydrateCuratedList($block, $preloadedData),
            HomePageBlockTypeEnum::DYNAMIC_LIST    => $this->hydrateDynamicList($block),
            HomePageBlockTypeEnum::BANNER          => $this->hydrateBanner($block),
            HomePageBlockTypeEnum::WEBINAR_BANNER  => $this->hydrateWebinarBanner($block, $preloadedData),
        };

        return new HomePageBlockData(
            id: $block->id,
            type: $block->type->value,
            title: $block->title,
            location: $block->location,
            content: $hydratedContent
        );
    }

    /**
     * @return array{products: \Illuminate\Support\Collection<int, Product>, categories: \Illuminate\Support\Collection<int, Category>}
     */
    private function preloadRequiredDataForBlock(HomePageBlock $block): array
    {
        $productIds  = collect();
        $categoryIds = collect();

        // Collect required IDs for this specific block
        match ($block->type) {
            HomePageBlockTypeEnum::MAIN_CATEGORIES => $categoryIds = $categoryIds->merge($block->content['items'] ?? []),
            HomePageBlockTypeEnum::CURATED_LIST    => $productIds  = $productIds->merge($block->content['items'] ?? []),
            HomePageBlockTypeEnum::WEBINAR_BANNER  => $productIds->push($block->content['product_id'] ?? null),
            default                                => null,
        };

        $uniqueProductIds = $productIds->unique()->values();
        $idsToFetch       = $uniqueProductIds->reject(fn (int $id): bool => $this->requestCache->hasProduct($id));

        // Pre-load products with relationships to avoid N+1
        if ($idsToFetch->isNotEmpty()) {
            $fetchedProducts = Product::query()
                ->publishedAndVisible()
                ->hasPublishedDeliveryOption()
                ->publishedProductable()
                ->activeTerm()
                ->forListing()
                ->whereIn('id', $idsToFetch)
                ->get()
                ->keyBy('id');
            $this->requestCache->storeProducts($fetchedProducts);
        }
        $products = $uniqueProductIds->map(fn (int $id): ?\App\Models\Product => $this->requestCache->getProduct($id))
            ->filter()->keyBy('id');

        // Pre-load categories with media
        $categories = $categoryIds->filter()->isNotEmpty()
            ? Category::whereIn('id', $categoryIds->unique()->values())
                ->with('media')
                ->get()
                ->keyBy('id')
            : collect();

        return [
            'products'   => $products,
            'categories' => $categories,
        ];
    }

    /**
     * @param  array{products: \Illuminate\Support\Collection<int, Product>, categories: \Illuminate\Support\Collection<int, Category>}  $preloadedData
     * @return array{items: array<int, array<string, mixed>>, preset: mixed}
     */
    private function hydrateCuratedList(HomePageBlock $block, array $preloadedData): array
    {
        /** @var array<int, int|string> $itemsIds */
        $itemsIds = $block->content['items'] ?? [];

        if ($block->type === HomePageBlockTypeEnum::MAIN_CATEGORIES) {
            $items = collect($itemsIds)
                ->map(fn ($id) => $preloadedData['categories']->get($id))
                ->filter()
                ->map(fn ($category): array => CategoryCardData::from($category)->toArray())
                ->values()
                ->all();
        } else {
            $items = collect($itemsIds)
                ->map(fn ($id) => $preloadedData['products']->get($id))
                ->filter()
                ->map(function (Product $product): array {
                    $priceData = $this->priceService->getPriceDataForProduct($product);

                    return ProductCardData::fromModel($product, $priceData)->toArray();
                })
                ->values()
                ->all();
        }

        return [
            'items'  => $items,
            'preset' => $block->content['preset'] ?? 'default',
        ];
    }

    /**
     * @return array{preset: mixed, items: array<int, array<string, mixed>>}
     */
    private function hydrateDynamicList(HomePageBlock $block): array
    {
        $entityType  = DynamicListEntityTypeEnum::from($block->content['entity_type']);
        $sortBy      = DynamicListSortByEnum::from($block->content['sort_by']);
        $limit       = $block->content['limit']        ?? 10;
        $preset      = $block->content['preset']       ?? 'default';
        $categoryIds = $block->content['category_ids'] ?? null;
        $query       = $this->buildQuery($entityType, $sortBy, $limit, $categoryIds);

        if ($entityType === DynamicListEntityTypeEnum::BLOG_POST) {
            // Blog posts don't use our product cache, so we fetch them normally.
            // A blog post cache could be added to RequestDataCache if needed.
            $entities = $query->get();
        } else {
            // For products, get the IDs first
            $productIds = $query->pluck('id');
            $idsToFetch = $productIds->reject(fn (int $id): bool => $this->requestCache->hasProduct($id));
            if ($idsToFetch->isNotEmpty()) {
                $fetchedProducts = $query
                    ->whereIn('id', $idsToFetch)
                    ->get();
                $this->requestCache->storeProducts($fetchedProducts);
            }
            $entities = $productIds->map(fn (int $id): ?\App\Models\Product => $this->requestCache->getProduct($id))
                ->filter();
        }

        return [
            'preset' => $preset,
            'items'  => $entities->map(function ($entity) use ($entityType): array {
                if ($entityType === DynamicListEntityTypeEnum::BLOG_POST) {
                    return BlogPostCardData::from($entity)->toArray();
                }
                /** @var Product $entity */
                $priceData = $this->priceService->getPriceDataForProduct($entity);

                return ProductCardData::fromModel($entity, $priceData)->toArray();
            })
                ->all(),
        ];
    }

    /**
     * @param  array<int, int|string>|null  $categoryIds
     * @return Builder<Product>|Builder<BlogPost>
     */
    private function buildQuery(
        DynamicListEntityTypeEnum $entityType,
        DynamicListSortByEnum $sortBy,
        int $limit,
        ?array $categoryIds
    ): Builder {

        $query = match ($entityType) {
            DynamicListEntityTypeEnum::COURSE_PRODUCTS => Product::query()
                ->publishedAndVisible()
                ->hasPublishedDeliveryOption()
                ->publishedProductable()
                ->activeTerm()
                ->ofType(ProductableEnum::COURSE)
                ->forListing(),

            DynamicListEntityTypeEnum::SEMINAR_PRODUCTS => Product::query()
                ->publishedAndVisible()
                ->hasPublishedDeliveryOption()
                ->publishedProductable()
                ->activeTerm()
                ->ofType(ProductableEnum::SEMINAR)
                ->forListing(),

            DynamicListEntityTypeEnum::DIGITAL_ASSET_PRODUCTS => Product::query()
                ->publishedAndVisible()
                ->hasPublishedDeliveryOption()
                ->publishedProductable()
                ->activeTerm()
                ->ofType(ProductableEnum::DIGITAL_ASSET)
                ->forListing(),

            DynamicListEntityTypeEnum::ALL_PRODUCTS => Product::query()
                ->publishedAndVisible()
                ->hasPublishedDeliveryOption()
                ->publishedProductable()
                ->activeTerm()
                ->forListing(),

            DynamicListEntityTypeEnum::BLOG_POST => BlogPost::query()
                ->where('status', \App\Enums\Content\PublicationStatusEnum::PUBLISHED)
                ->with(['author']),
        };

        // Apply category filtering if specified
        if ($categoryIds
            && in_array($entityType, [
                DynamicListEntityTypeEnum::COURSE_PRODUCTS,
                DynamicListEntityTypeEnum::SEMINAR_PRODUCTS,
                DynamicListEntityTypeEnum::DIGITAL_ASSET_PRODUCTS,
                DynamicListEntityTypeEnum::ALL_PRODUCTS,
            ])
        ) {
            $query->whereHas('categories', fn (Builder $categoryQuery): Builder => $categoryQuery
                ->whereIn('categories.id', $categoryIds));
        }

        // Apply sorting
        match ($sortBy) {
            DynamicListSortByEnum::CREATED_AT_DESC => $this->applySorting($query, 'created_at', 'desc'),
            DynamicListSortByEnum::CREATED_AT_ASC  => $this->applySorting($query, 'created_at', 'asc'),
            DynamicListSortByEnum::UPDATED_AT_DESC => $this->applySorting($query, 'updated_at', 'desc'),
            DynamicListSortByEnum::UPDATED_AT_ASC  => $this->applySorting($query, 'updated_at', 'asc'),
            DynamicListSortByEnum::NAME_ASC        => $this->applySorting($query, 'name', 'asc'),
            DynamicListSortByEnum::NAME_DESC       => $this->applySorting($query, 'name', 'desc'),
            DynamicListSortByEnum::POPULAR         => $this->applyPopularSorting($query, $entityType),
            DynamicListSortByEnum::FEATURED        => $this->applyFeaturedSorting($query, $entityType),
        };

        return $query->limit($limit);
    }

    /**
     * @param  Builder<*>  $query
     */
    private function applySorting(Builder $query, string $field, string $direction): void
    {
        $query->orderBy($field, $direction);
    }

    /**
     * @param  Builder<*>  $query
     */
    private function applyPopularSorting(Builder $query, DynamicListEntityTypeEnum $entityType): void
    {
        if ($entityType === DynamicListEntityTypeEnum::BLOG_POST) {
            // For blog posts, we could sort by view count if we had that field
            $query->orderBy('created_at', 'desc');
        } else {
            $query->withCount('orderItems')->orderByDesc('order_items_count');
        }
    }

    /**
     * @param  Builder<*>  $query
     */
    private function applyFeaturedSorting(Builder $query, DynamicListEntityTypeEnum $entityType): void
    {
        $query->where('is_featured', true)->orderByDesc('created_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function hydrateBanner(HomePageBlock $block): array
    {
        return BannerData::from($block->content)->toArray();
    }

    /**
     * @param  array{products: \Illuminate\Support\Collection<int, Product>, categories: \Illuminate\Support\Collection<int, Category>}  $preloadedData
     * @return array<string, mixed>
     */
    private function hydrateWebinarBanner(HomePageBlock $block, array $preloadedData): array
    {
        $productId = $block->content['product_id'] ?? null;
        $product   = $productId ? $preloadedData['products']->get($productId) : null;
        $priceData = $product ? $this->priceService->getPriceDataForProduct($product) : null;

        return WebinarBannerData::fromBlock($block->content, $product, $priceData)->toArray();
    }
}

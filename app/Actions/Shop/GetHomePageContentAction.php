<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Data\Shop\HomePageContentData;
use App\Data\Shop\ProductPriceData;
use App\Enums\DynamicListEntityTypeEnum;
use App\Enums\DynamicListSortByEnum;
use App\Enums\HomePageBlockTypeEnum;
use App\Enums\ProductableEnum;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\HomePageBlock;
use App\Models\Product;
use App\Services\ProductPriceService;
use App\Services\RequestDataCacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class GetHomePageContentAction
{
    public function __construct(
        private ProductPriceService $priceService,
        private RequestDataCacheService $requestCache
    ) {
    }

    public function handle(): HomePageContentData
    {
        $blocks = HomePageBlock::query()
            ->where('is_active', true)
            ->orderBy('location')
            ->orderBy('order')
            ->get();

        $heroBlocks = $blocks->where('location', 'hero');
        $mainContentBlocks = $blocks->where('location', '!=', 'hero');

        // Pre-load all required data to avoid N+1 queries
        $preloadedData = $this->preloadAllRequiredData($blocks);

        return new HomePageContentData(
            hero: $this->hydrateBlocks($heroBlocks, $preloadedData),
            main_content: $this->hydrateBlocks($mainContentBlocks, $preloadedData)
        );
    }

    private function preloadAllRequiredData(Collection $blocks): array
    {
        $productIds = collect();
        $categoryIds = collect();

        // Collect all required IDs from all blocks
        foreach ($blocks as $block) {
            match ($block->type) {
                HomePageBlockTypeEnum::MAIN_CATEGORIES => $categoryIds = $categoryIds->merge($block->content['items'] ??
                    []),
                HomePageBlockTypeEnum::CURATED_LIST => $productIds = $productIds->merge($block->content['items'] ?? []),
                HomePageBlockTypeEnum::WEBINAR_BANNER => $productIds->push($block->content['product_id'] ?? null),
                default => null,
            };
        }

        $uniqueProductIds = $productIds->unique()->values();
        $idsToFetch = $uniqueProductIds->filter(fn($id) => !$this->requestCache->hasProduct($id));

        // Pre-load all products with relationships to avoid N+1
        if ($idsToFetch->isNotEmpty()) {
            $fetchedProducts = Product::whereIn('id', $productIds->unique()->values())
                ->activeWithData()
                ->get()
                ->keyBy('id');
            $this->requestCache->storeProducts($fetchedProducts);
        }
        $products = $uniqueProductIds->map(fn($id) => $this->requestCache->getProduct($id))->filter()->keyBy('id');
        // Pre-load all categories with media
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

    private function hydrateBlocks(Collection $blocks, array $preloadedData): array
    {
        return $blocks->map(function (HomePageBlock $block) use ($preloadedData) {
            $hydratedContent = match ($block->type) {
                HomePageBlockTypeEnum::MAIN_CATEGORIES => $this->hydrateCuratedList($block, $preloadedData),
                HomePageBlockTypeEnum::CURATED_LIST => $this->hydrateCuratedList($block, $preloadedData),
                HomePageBlockTypeEnum::DYNAMIC_LIST => $this->hydrateDynamicList($block),
                HomePageBlockTypeEnum::BANNER => $this->hydrateBanner($block),
                HomePageBlockTypeEnum::WEBINAR_BANNER => $this->hydrateWebinarBanner($block, $preloadedData),
            };

            return [
                'type'    => $block->type->value,
                'title'   => $block->title,
                'content' => $hydratedContent,
            ];
        })->values()->toArray();
    }

    private function hydrateCuratedList(HomePageBlock $block, array $preloadedData): array
    {
        $itemsIds = $block->content['items'] ?? [];

        if ($block->type === HomePageBlockTypeEnum::MAIN_CATEGORIES) {
            $items = collect($itemsIds)
                ->map(fn($id) => $preloadedData['categories']->get($id))
                ->filter()
                ->map(fn($category) => $this->formatEntity($category, 'categories', $preloadedData))
                ->values()
                ->toArray();
        } else {
            $items = collect($itemsIds)
                ->map(fn($id) => $preloadedData['products']->get($id))
                ->filter()
                ->map(fn($product) => $this->formatEntity($product, 'products', $preloadedData))
                ->values()
                ->toArray();
        }

        return [
            'items'  => $items,
            'preset' => $block->content['preset'] ?? 'default',
        ];
    }

    private function hydrateDynamicList(HomePageBlock $block): array
    {
        $entityType = DynamicListEntityTypeEnum::from($block->content['entity_type']);
        $sortBy = DynamicListSortByEnum::from($block->content['sort_by']);
        $limit = $block->content['limit'] ?? 10;
        $preset = $block->content['preset'] ?? 'default';
        $categoryIds = $block->content['category_ids'] ?? null;
        $query = $this->buildQuery($entityType, $sortBy, $limit, $categoryIds);
        if ($entityType === DynamicListEntityTypeEnum::BLOG_POST) {
            // Blog posts don't use our product cache, so we fetch them normally.
            // A blog post cache could be added to RequestDataCache if needed.
            $entities = $query->get();
        } else {
            // For products, get the IDs first
            $productIds = $query->pluck('id');
            $idsToFetch = $productIds->filter(fn($id) => !$this->requestCache->hasProduct($id));
            if ($idsToFetch->isNotEmpty()) {
                $fetchedProducts = $query
                    ->whereIn('id', $idsToFetch)
                    ->get();
                $this->requestCache->storeProducts($fetchedProducts);
            }
            $entities = $productIds->map(fn($id) => $this->requestCache->getProduct($id))->filter();
        }

        $formatType = match ($entityType) {
            DynamicListEntityTypeEnum::BLOG_POST => 'blog_posts',
            default => 'products',
        };

        return [
            'preset' => $preset,
            'items'  => $entities->map(fn($entity) => $this->formatEntity($entity, $formatType))
                ->toArray(),
        ];
    }

    private function buildQuery(
        DynamicListEntityTypeEnum $entityType,
        DynamicListSortByEnum $sortBy,
        int $limit,
        ?array $categoryIds
    ): Builder {
        $baseProductQuery = Product::query()
            ->activeWithData();
        $query = match ($entityType) {
            DynamicListEntityTypeEnum::COURSE_PRODUCTS => $baseProductQuery
                ->where('productable_type', ProductableEnum::COURSE),

            DynamicListEntityTypeEnum::SEMINAR_PRODUCTS => $baseProductQuery
                ->where('productable_type', ProductableEnum::SEMINAR),

            DynamicListEntityTypeEnum::DIGITAL_ASSET_PRODUCTS => $baseProductQuery
                ->where('productable_type', ProductableEnum::DIGITAL_ASSET),

            DynamicListEntityTypeEnum::ALL_PRODUCTS => $baseProductQuery,

            DynamicListEntityTypeEnum::BLOG_POST => BlogPost::query()
                ->where('status', \App\Enums\PublicationStatusEnum::PUBLISHED)
                ->with(['author', 'media']),
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
            $query->whereHas('categories', fn(Builder $q) => $q->whereIn('categories.id', $categoryIds));
        }

        // Apply sorting
        match ($sortBy) {
            DynamicListSortByEnum::CREATED_AT_DESC => $query->orderBy('created_at', 'desc'),
            DynamicListSortByEnum::CREATED_AT_ASC => $query->orderBy('created_at', 'asc'),
            DynamicListSortByEnum::UPDATED_AT_DESC => $query->orderBy('updated_at', 'desc'),
            DynamicListSortByEnum::UPDATED_AT_ASC => $query->orderBy('updated_at', 'asc'),
            DynamicListSortByEnum::NAME_ASC => $query->orderBy('name', 'asc'),
            DynamicListSortByEnum::NAME_DESC => $query->orderBy('name', 'desc'),
            DynamicListSortByEnum::POPULAR => $this->applyPopularSorting($query, $entityType),
            DynamicListSortByEnum::FEATURED => $query->where('is_featured', true)->orderBy('created_at', 'desc'),
        };
        return $query->limit($limit);
    }

    private function applyPopularSorting(Builder $query, DynamicListEntityTypeEnum $entityType): void
    {
        if ($entityType === DynamicListEntityTypeEnum::BLOG_POST) {
            // For blog posts, we could sort by view count if we had that field
            $query->orderBy('created_at', 'desc');
        } else {
            // For products, sort by order count
            $query->withCount('orderItems')->orderBy('order_items_count', 'desc');
        }
    }

    private function hydrateBanner(HomePageBlock $block): array
    {
        return [
            'image_url'    => $block->content['image_url'] ?? null,
            'action'       => $block->content['action'] ?? '',
            'action_title' => $block->content['action_title'] ?? '',
            'content'      => $block->content['content'] ?? null,
            'preset'       => $block->content['preset'] ?? 'default',
        ];
    }

    private function hydrateWebinarBanner(HomePageBlock $block, array $preloadedData): array
    {
        $productId = $block->content['product_id'] ?? null;
        $product = $productId ? $preloadedData['products']->get($productId) : null;

        return [
            'text'      => $block->content['text'] ?? '',
            'image_url' => $block->content['image_url'] ?? null,
            'product'   => $product ? $this->formatEntity($product, 'seminar', $preloadedData) : null,
        ];
    }

    /**
     * @param $entity
     * @param  string  $type
     * @param  array  $preloadedData
     *
     * @return array
     */
    private function formatEntity($entity, string $type, array $preloadedData = []): array
    {
        if (in_array($type, ['products', 'seminar'])) {
            $priceData = $entity->price_data_cache
                ? ProductPriceData::from(json_decode($entity->price_data_cache, true))
                : $this->priceService->getPriceDataForProduct($entity);
        }
        return match ($type) {
            'categories' => [
                'id'        => $entity->id,
                'name'      => $entity->name,
                'slug'      => $entity->slug ?? null,
                'icon_url'  => $entity->icon_url,
                'image_url' => $entity->image_url,
                'link'      => "/categories/{$entity->slug}",
            ],
            'products' => [
                'id'               => $entity->id,
                'slug'             => $entity->slug,
                'name'             => $entity->name,
                'price'            => $priceData?->min_price,
                'original_price'   => $priceData?->min_original_price,
                'price_data'       => $priceData,
                'price_range'      => $priceData?->range,
                'has_discount'     => $priceData?->has_discount,
                'discount_percent' => $priceData?->discount_percentage,
                'is_free'          => ($priceData?->current_price ?? 0) <= 0,
                'is_featured'      => $entity->is_featured,
                'product_type'     => $entity->productable_type,
                'thumbnail_url'  => $this->getProductCoverImage($entity),
                'teacher_name'     => $entity->productable?->default_teacher_info ??
                        $entity->vendor?->name ?? 'Unknown',
                'link'             => "/products/{$entity->id}",
            ],
            'seminar' => [
                'id'                    => $entity->id,
                'slug'                  => $entity->slug,
                'name'                  => $entity->name,
                'price'                 => $priceData?->min_price,
                'original_price'        => $priceData?->min_original_price,
                'price_data'            => $priceData,
                'has_discount'          => $priceData?->has_discount,
                'discount_percent'      => $priceData?->discount_percentage,
                'is_free'               => ($priceData?->current_price ?? 0) <= 0,
                'is_featured'           => $entity->is_featured,
                'product_type'          => $entity->productable_type,
                'thumbnail_url'       => $this->getProductCoverImage($entity),
                'teacher_name'          => $entity->productable?->default_teacher_info ?? $entity->vendor?->name ?? '',
                'link'                  => "/seminar/{$entity->id}",
                'start_date'            => data_get($entity, 'details_json.start_date') ? verta(data_get($entity,
                    'details_json.start_date'))->format('Y-m-d H:i:s') : null,
                'end_date'              => data_get($entity, 'details_json.end_date') ? verta(data_get($entity,
                    'details_json.end_date'))->format('Y-m-d H:i:s') : null,
                'location'              => data_get($entity, 'details_json.location'),
                'registration_deadline' => data_get($entity, 'details_json.registration_deadline'),
            ],
            'blog_posts' => [
                'id'              => $entity->id,
                'title'           => $entity->title,
                'slug'            => $entity->slug,
                'excerpt'         => $entity->excerpt,
                'author_name'     => $entity->author?->name ?? 'Unknown',
                'published_at'    => data_get($entity, 'details_json.published_at') ? verta(data_get($entity,
                    'details_json.published_at'))->format('Y-m-d') : null,
                'thumbnail_url' => $entity->relationLoaded('media') ? $entity->firstMedia('main') : null,
                'link'            => "/blog/{$entity->slug}",
            ],
            default => [],
        };
    }

    private function getProductCoverImage($product): ?string
    {
        //dd($product->productable);
        return  $product->productable->thumbnail_url;
        //if ($product->productable) {
        //    // Check if the productable has the getProductableCover method (IsProductable trait)
        //    if (method_exists($product->productable, 'getCoverMedia')) {
        //        $coverImage = $product->productable->getCoverMedia();
        //        if ($coverImage && count($coverImage) > 0) {
        //            return data_get($coverImage, '0.url', null);
        //        }
        //    }
        //}
        //
        //return null;
    }
}

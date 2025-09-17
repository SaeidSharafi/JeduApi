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
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\HomePageBlock;
use App\Models\Product;
use App\Models\Seminar;
use App\Services\ProductPriceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

final readonly class GetHomePageContentAction
{
    public function __construct(
        private ProductPriceService $priceService
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

        // Pre-load all products with relationships to avoid N+1
        $products = $productIds->filter()->isNotEmpty()
            ? Product::whereIn('id', $productIds->unique()->values())
                ->with([
                    'vendor',
                    'productable.media',
                    'productDeliveryOptions:id,product_id,price,featured_price,is_featured,featured_price_start_date,featured_price_end_date',
                    'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
                ])
                ->get()
                ->keyBy('id')
            : collect();

        // Pre-load all categories with media
        $categories = $categoryIds->filter()->isNotEmpty()
            ? Category::whereIn('id', $categoryIds->unique()->values())
                ->with('media')
                ->get()
                ->keyBy('id')
            : collect();

        // Pre-calculate all pricing data to avoid N+1 in formatEntity
        $productPricing = [];
        foreach ($products as $product) {
            $priceData = $this->priceService->getPriceData($product);

            $productPricing[$product->id] = $priceData;
        }

        return [
            'products'        => $products,
            'categories'      => $categories,
            'product_pricing' => $productPricing,
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

        $entities = $this->executeQuery($entityType, $sortBy, $limit, $categoryIds);

        $formatType = match ($entityType) {
            DynamicListEntityTypeEnum::BLOG_POST => 'blog_posts',
            default => 'products',
        };

        // Pre-calculate pricing for dynamic products to avoid N+1
        $entityPricing = [];
        if ($formatType === 'products') {
            foreach ($entities as $entity) {
                $priceData = $this->priceService->getPriceData($entity);
                $entityPricing[$entity->id] = $priceData;
            }
        }

        $dynamicPreloadedData = [
            'product_pricing' => $entityPricing,
        ];

        return [
            'preset' => $preset,
            'items'  => $entities->map(fn($entity) => $this->formatEntity($entity, $formatType, $dynamicPreloadedData))
                ->toArray(),
        ];
    }

    private function executeQuery(
        DynamicListEntityTypeEnum $entityType,
        DynamicListSortByEnum $sortBy,
        int $limit,
        ?array $categoryIds
    ): Collection {
        $query = match ($entityType) {
            DynamicListEntityTypeEnum::COURSE_PRODUCTS => Product::query()
                ->where('productable_type', ProductableEnum::COURSE)
                ->with([
                    'vendor',
                    'productable.media',
                    'productDeliveryOptions:id,product_id,price,featured_price,is_featured,featured_price_start_date,featured_price_end_date',
                    'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
                ]),

            DynamicListEntityTypeEnum::SEMINAR_PRODUCTS => Product::query()
                ->where('productable_type', ProductableEnum::SEMINAR)
                ->with([
                    'vendor',
                    'productable.media',
                    'productDeliveryOptions:id,product_id,price,featured_price,is_featured,featured_price_start_date,featured_price_end_date',
                    'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
                ]),

            DynamicListEntityTypeEnum::DIGITAL_ASSET_PRODUCTS => Product::query()
                ->where('productable_type', ProductableEnum::DIGITAL_ASSET)
                ->with([
                    'vendor',
                    'productable.media',
                    'productDeliveryOptions:id,product_id,price,featured_price,is_featured,featured_price_start_date,featured_price_end_date',
                    'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
                ]),

            DynamicListEntityTypeEnum::ALL_PRODUCTS => Product::query()
                ->with([
                    'vendor',
                    'productable.media',
                    'productDeliveryOptions:id,product_id,price,featured_price,is_featured,featured_price_start_date,featured_price_end_date',
                    'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
                ]),

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
        return $query->limit($limit)->get();
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
        /* @var ProductPriceData $priceData */
        $priceData = $preloadedData['product_pricing'][$entity->id] ?? null;
        return match ($type) {
            'categories' => [
                'id'        => $entity->id,
                'name'      => $entity->name,
                'slug'      => $entity->slug ?? null,
                'icon_url'  => $entity->icon_url
                    ?: ($entity->relationLoaded('media') ? $entity->getFirstMediaUrl('icon') : null),
                'image_url' => $entity->image_url
                    ?: ($entity->relationLoaded('media') ? $entity->getFirstMediaUrl('image') : null),
                'link'      => "/categories/{$entity->slug}",
            ],
            'products' => [
                'id'              => $entity->id,
                'name'            => $entity->name,
                'price'           => $priceData->current_price ?? $this->priceService->getCurrentPrice($entity),
                'original_price'  => $priceData->original_price ??
                    ($entity->productDeliveryOptions()->first()?->price ?? 0),
                'has_discount'    => $priceData->has_discount ?? $this->priceService->hasActiveDiscount($entity),
                'discount_percent'=> $priceData->discount_percentage ??
                    $this->priceService->getDiscountPercentage($entity),
                'is_free'         => ($priceData->current_price ?? 0) <= 0,
                'is_featured'     => $entity->is_featured,
                'product_type'    => $entity->productable_type,
                'cover_image_url' => $this->getProductCoverImage($entity),
                'teacher_name'    => $entity->productable?->default_teacher_info ?? $entity->vendor?->name ?? 'Unknown',
                'link'            => "/products/{$entity->id}",
            ],
            'seminar' => [
                'id'              => $entity->id,
                'name'            => $entity->name,
                'price'           => $priceData->current_price ?? $this->priceService->getCurrentPrice($entity),
                'original_price'  => $priceData->original_price ??
                    ($entity->productDeliveryOptions()->first()?->price ?? 0),
                'has_discount'    => $priceData->has_discount ?? $this->priceService->hasActiveDiscount($entity),
                'discount_percent'=> $priceData->discount_percentage ??
                    $this->priceService->getDiscountPercentage($entity),
                'is_free'         => ($priceData->current_price ?? 0) <= 0,
                'is_featured'     => $entity->is_featured,
                'product_type'    => $entity->productable_type,
                'cover_image_url' => $this->getProductCoverImage($entity),
                'teacher_name'    => $entity->productable?->default_teacher_info ?? $entity->vendor?->name ?? '',
                'link'            => "/seminar/{$entity->id}",
                'start_date'     => data_get($entity,'details_json.start_date'),
                'end_date'       => data_get($entity,'details_json.end_date'),
                'location'       => data_get($entity,'details_json.location'),
                'registration_deadline' => data_get($entity,'details_json.registration_deadline'),
            ],
            'blog_posts' => [
                'id'              => $entity->id,
                'title'           => $entity->title,
                'slug'            => $entity->slug,
                'excerpt'         => $entity->excerpt,
                'author_name'     => $entity->author?->name ?? 'Unknown',
                'published_at'    => $entity->published_at?->toISOString(),
                'cover_image_url' => $entity->relationLoaded('media') ? $entity->getFirstMediaUrl('cover') : null,
                'link'            => "/blog/{$entity->slug}",
            ],
            default => [],
        };
    }

    private function getProductCoverImage($product): ?string
    {
        // Try to get cover image from productable media
        if ($product->productable && $product->productable->relationLoaded('media')) {
            // Check if the productable has the getProductableCover method (IsProductable trait)
            if (method_exists($product->productable, 'getProductableCover')) {
                $coverImage = $product->productable->getProductableCover();
                if ($coverImage && count($coverImage) > 0) {
                    return data_get($coverImage, '0.url', null);
                }
            }
        }

        return null;
    }
}

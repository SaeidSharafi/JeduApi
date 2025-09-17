<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Data\Shop\HomePageContentData;
use App\Enums\DynamicListEntityTypeEnum;
use App\Enums\DynamicListSortByEnum;
use App\Enums\HomePageBlockTypeEnum;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\HomePageBlock;
use App\Models\Product;
use App\Models\Seminar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class GetHomePageContentAction
{
    public function __construct(
        private ProductPriceAction $priceAction
    ) {}

    public function handle(): HomePageContentData
    {
        $blocks = HomePageBlock::query()
            ->where('is_active', true)
            ->orderBy('location')
            ->orderBy('order')
            ->get();

        $heroBlocks = $blocks->where('location', 'hero');
        $mainContentBlocks = $blocks->where('location', '!=', 'hero');

        return new HomePageContentData(
            hero: $this->hydrateBlocks($heroBlocks),
            main_content: $this->hydrateBlocks($mainContentBlocks)
        );
    }

    private function hydrateBlocks(Collection $blocks): array
    {
        return $blocks->map(function (HomePageBlock $block) {
            $hydratedContent = match ($block->type) {
                HomePageBlockTypeEnum::MAIN_CATEGORIES,
                HomePageBlockTypeEnum::CURATED_LIST => $this->hydrateCuratedList($block),
                HomePageBlockTypeEnum::DYNAMIC_LIST => $this->hydrateDynamicList($block),
                HomePageBlockTypeEnum::BANNER => $this->hydrateBanner($block),
                HomePageBlockTypeEnum::WEBINAR_BANNER => $this->hydrateWebinarBanner($block),
            };

            return [
                'type' => $block->type->value,
                'title' => $block->title,
                'content' => $hydratedContent,
            ];
        })->values()->toArray(); // Add values() to reindex the array
    }

    private function hydrateCuratedList(HomePageBlock $block): array
    {
        $itemsIds = $block->content['items'] ?? [];

        if ($block->type === HomePageBlockTypeEnum::MAIN_CATEGORIES) {
            // Load categories with media
            $categories = Category::whereIn('id', $itemsIds)
                ->with('media')
                ->get()
                ->keyBy('id');

            $items = collect($itemsIds)
                ->map(fn($id) => $categories->get($id))
                ->filter()
                ->map(fn($category) => $this->formatEntity($category, 'categories'))
                ->values()
                ->toArray();
        } else {
            // Load products with all necessary relationships for pricing
            $products = Product::whereIn('id', $itemsIds)
                ->with([
                    'vendor',
                    'productable.media',
                    'productDeliveryOptions:id,product_id,price,featured_price,is_featured,featured_price_start_date,featured_price_end_date',
                    'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
                ])
                ->get()
                ->keyBy('id');

            $items = collect($itemsIds)
                ->map(fn($id) => $products->get($id))
                ->filter()
                ->map(fn($product) => $this->formatEntity($product, 'products'))
                ->values()
                ->toArray();
        }

        return [
            'items' => $items,
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

        return [
            'preset' => $preset,
            'items' => $entities->map(fn($entity) => $this->formatEntity($entity, $formatType))->toArray(),
        ];
    }

    private function executeQuery(DynamicListEntityTypeEnum $entityType, DynamicListSortByEnum $sortBy, int $limit, ?array $categoryIds): Collection
    {
        $query = match ($entityType) {
            DynamicListEntityTypeEnum::COURSE_PRODUCTS => Product::query()
                ->where('productable_type', Course::class)
                ->with([
                    'vendor',
                    'productable.media',
                    'productDeliveryOptions:id,product_id,price,featured_price,is_featured,featured_price_start_date,featured_price_end_date',
                    'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
                ]),

            DynamicListEntityTypeEnum::SEMINAR_PRODUCTS => Product::query()
                ->where('productable_type', Seminar::class)
                ->with([
                    'vendor',
                    'productable.media',
                    'productDeliveryOptions:id,product_id,price,featured_price,is_featured,featured_price_start_date,featured_price_end_date',
                    'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
                ]),

            DynamicListEntityTypeEnum::DIGITAL_ASSET_PRODUCTS => Product::query()
                ->where('productable_type', DigitalAsset::class)
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
        if ($categoryIds && in_array($entityType, [
            DynamicListEntityTypeEnum::COURSE_PRODUCTS,
            DynamicListEntityTypeEnum::SEMINAR_PRODUCTS,
            DynamicListEntityTypeEnum::DIGITAL_ASSET_PRODUCTS,
            DynamicListEntityTypeEnum::ALL_PRODUCTS,
        ])) {
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
            'image_url' => $block->content['image_url'] ?? null,
            'action' => $block->content['action'] ?? '',
            'action_title' => $block->content['action_title'] ?? '',
            'content' => $block->content['content'] ?? null,
            'preset' => $block->content['preset'] ?? 'default',
        ];
    }

    private function hydrateWebinarBanner(HomePageBlock $block): array
    {
        $productId = $block->content['product_id'] ?? null;
        $product = $productId ? Product::with([
            'vendor',
            'productable',
            'productDeliveryOptions:id,product_id,price,featured_price,is_featured,featured_price_start_date,featured_price_end_date',
            'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
        ])->find($productId) : null;

        return [
            'text' => $block->content['text'] ?? '',
            'image_url' => $block->content['image_url'] ?? null,
            'product' => $product ? $this->formatEntity($product, 'products') : null,
        ];
    }


    private function formatEntity($entity, string $type): array
    {
        return match ($type) {
            'categories' => [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug ?? null,
                'icon_url' => $entity->icon_url ?: ($entity->relationLoaded('media') ? $entity->getFirstMediaUrl('icon') : null),
                'image_url' => $entity->image_url ?: ($entity->relationLoaded('media') ? $entity->getFirstMediaUrl('image') : null),
                'link' => "/categories/{$entity->slug}",
            ],
            'products' => [
                'id' => $entity->id,
                'name' => $entity->name,
                'price' => $this->priceAction->getCurrentPrice($entity),
                'original_price' => $entity->productDeliveryOptions()->first()?->price ?? 0,
                'has_discount' => $this->priceAction->hasActiveDiscount($entity),
                'cover_image_url' => $this->getProductCoverImage($entity),
                'teacher_name' => $entity->productable?->default_teacher_info ?? $entity->vendor?->name ?? 'Unknown',
                'link' => "/products/{$entity->id}",
            ],
            'blog_posts' => [
                'id' => $entity->id,
                'title' => $entity->title,
                'slug' => $entity->slug,
                'excerpt' => $entity->excerpt,
                'author_name' => $entity->author?->name ?? 'Unknown',
                'published_at' => $entity->published_at?->toISOString(),
                'cover_image_url' => $entity->relationLoaded('media') ? $entity->getFirstMediaUrl('cover') : null,
                'link' => "/blog/{$entity->slug}",
            ],
            default => [],
        };
    }

    private function getProductCoverImage($product): ?string
    {
        // Try to get cover image from productable media
        if ($product->productable && $product->productable->relationLoaded('media')) {
            // Check if the productable has the getFirstMediaUrl method (Mediable trait)
            if (method_exists($product->productable, 'getFirstMediaUrl')) {
                $coverImage = $product->productable->getFirstMediaUrl('cover');
                if ($coverImage) {
                    return $coverImage;
                }
            }
        }

        return null;
    }
}

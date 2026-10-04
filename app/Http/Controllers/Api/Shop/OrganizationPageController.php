<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Contracts\ApiResponseInterface;
use App\Contracts\Cache\CacheStore;
use App\Data\Shop\Organization\OrganizationPageData;
use App\Data\Shop\Organization\OrganizationPageRequestData;
use App\Data\Shop\Product\ProductCardData;
use App\Data\Shop\ProductPriceData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\OrganizationPage;
use App\Models\Product;
use App\Services\ProductPriceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @group Shop - Organization
 *
 * @unauthenticated
 */
final class OrganizationPageController extends Controller
{
    public function __construct(
        private readonly CacheStore $cache,
    ) {}

    /**
     * Get the public Organization page and recent department Courses.
     *
     * @responseFile 200 resources/responses/shop/organization/show.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(
        OrganizationPageRequestData $data,
        ProductPriceService $priceService,
    ): ApiResponseInterface {
        $limit = $data->limit ?? 6;

        $page = $this->cache->flexible(
            CacheKey::OrganizationPage,
            ['limit' => $limit],
            function () use ($limit, $priceService): OrganizationPageData {
                $page = OrganizationPage::query()->singleton()->with('vendor')->firstOrFail();

                if ($page->vendor === null) {
                    return OrganizationPageData::fromModel($page, collect());
                }

                $courses = Course::query()
                    ->where('courses.status', PublicationStatusEnum::PUBLISHED)
                    ->whereHas('products', function (Builder $query) use ($page): void {
                        $query->whereBelongsTo($page->vendor);
                    })
                    ->orderByDesc('courses.created_at')
                    ->orderByDesc('courses.id')
                    ->limit($limit)
                    ->get();

                $eligibleProducts = Product::query()
                    ->whereBelongsTo($page->vendor)
                    ->ofType(ProductableEnum::COURSE)
                    ->publishedAndVisible()
                    ->hasPublishedDeliveryOption()
                    ->publishedProductable()
                    ->activeTerm();

                /** @var Collection<int, Collection<int, Product>> $productsByCourse */
                $productsByCourse = $eligibleProducts
                    ->whereIn('products.productable_id', $courses->pluck('id'))
                    ->forListing()
                    ->orderByDesc('products.created_at')
                    ->orderByDesc('products.id')
                    ->get()
                    ->groupBy('productable_id');

                /** @var Collection<int, Product> $products */
                $products = $productsByCourse
                    ->map(fn (Collection $courseProducts): Product => $courseProducts->first())
                    ->values();
                $priceData = $priceService->getPriceDataForProducts($products);

                /** @var Collection<int, ProductCardData> $recentCourses */
                $recentCourses = $courses->map(function (Course $course) use ($priceData, $productsByCourse): ProductCardData {
                    $product = $productsByCourse->get($course->id)?->first();

                    /** @var ProductPriceData|null $productPriceData */
                    $productPriceData = $product ? $priceData->get($product->id) : null;

                    return ProductCardData::fromCourse($course, $product, $productPriceData);
                })
                    ->values();

                return OrganizationPageData::fromModel($page, $recentCourses);
            },
        );

        return apiResponse()->success($page);
    }
}

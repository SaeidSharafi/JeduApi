<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\LearningPath;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\LearningPath\LearningPathCardData;
use App\Data\Shop\LearningPath\LearningPathDetailData;
use App\Data\Shop\PaginationRequestData;
use App\Data\Shop\ProductPriceData;
use App\Enums\Content\PublicationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\LearningPath;
use App\Models\LearningPathStep;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Seminar;
use App\Services\ProductPriceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @group Shop - Learning Paths
 *
 * @unauthenticated
 *
 * Public, non-commercial Learning Path catalog APIs.
 */
final class LearningPathController extends Controller
{
    /**
     * List published Learning Paths in their public display order.
     *
     * @responseFile 200 resources/responses/shop/learning-paths/index.json
     */
    public function index(PaginationRequestData $requestData): ApiResponseInterface
    {
        $learningPaths = LearningPath::query()
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->withProductableMedia()
            ->withCount('steps')
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(
                perPage: $requestData->per_page ?? 15,
                page: $requestData->page,
            )
            ->withQueryString();

        return apiResponse()->success(LearningPathCardData::collect($learningPaths));
    }

    /**
     * Get a published Learning Path by its public slug.
     *
     * @urlParam slug string required The public Learning Path slug. Example: backend-learning-path
     *
     * @responseFile 200 resources/responses/shop/learning-paths/show.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(
        string $slug,
        ProductPriceService $priceService,
    ): ApiResponseInterface {
        $learningPath = LearningPath::query()
            ->where('slug', $slug)
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->withProductableMedia()
            ->with('steps.productable')
            ->firstOrFail();

        $learningPath->steps->loadMorph('productable', [
            Course::class       => ['media'],
            Seminar::class      => ['media'],
            DigitalAsset::class => ['media'],
        ]);

        $this->resolveCurrentProducts($learningPath, $priceService);

        return apiResponse()->success(LearningPathDetailData::fromModel($learningPath));
    }

    private static function referenceKey(string $type, int $id): string
    {
        return $type.':'.$id;
    }

    private function resolveCurrentProducts(LearningPath $learningPath, ProductPriceService $priceService): void
    {
        $references = $learningPath->steps
            ->map(static fn (LearningPathStep $step): array => [
                'type' => (string) $step->productable_type,
                'id'   => (int) $step->productable_id,
            ])
            ->groupBy('type')
            ->map(static fn (Collection $items): array => $items->pluck('id')->unique()->values()->all());

        if ($references->isEmpty()) {
            $this->setStepResolutions($learningPath->steps, collect(), collect());

            return;
        }

        $products = Product::query()
            ->publishedAndVisible()
            ->hasPublishedDeliveryOption()
            ->publishedProductable()
            ->activeTerm()
            ->forListing()
            ->where(function (Builder $query) use ($references): void {
                $first = true;
                foreach ($references as $type => $ids) {
                    $method = $first ? 'where' : 'orWhere';
                    $query->{$method}(function (Builder $typeQuery) use ($type, $ids): void {
                        $typeQuery
                            ->where('productable_type', $type)
                            ->whereIn('productable_id', $ids);
                    });
                    $first = false;
                }
            })
            ->get()
            ->keyBy(fn (Product $product): string => self::referenceKey(
                (string) $product->productable_type,
                (int) $product->productable_id,
            ));

        $availableProductIds = ProductDeliveryOption::query()
            ->whereIn('product_id', $products->modelKeys())
            ->availableWithCapacity()
            ->pluck('product_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values();

        $priceData = $products->mapWithKeys(function (Product $product) use ($priceService): array {
            return [
                $product->id => $priceService->getPriceDataForProduct($product),
            ];
        });

        $this->setStepResolutions($learningPath->steps, $products, $availableProductIds, $priceData);
    }

    /**
     * @param  Collection<int, LearningPathStep>  $steps
     * @param  Collection<string, Product>  $products
     * @param  Collection<int, int>  $availableProductIds
     * @param  Collection<int, ProductPriceData>  $priceData
     */
    private function setStepResolutions(
        Collection $steps,
        Collection $products,
        Collection $availableProductIds,
        Collection $priceData = new Collection(),
    ): void {
        foreach ($steps as $step) {
            $product = $products->get(self::referenceKey(
                (string) $step->productable_type,
                (int) $step->productable_id,
            ));

            $step->setRelation('currentProduct', $product);
            $step->setAttribute(
                'current_product_action_enabled',
                $product instanceof Product && $availableProductIds->contains($product->id),
            );
            $step->setAttribute(
                'current_product_price_data',
                $product instanceof Product ? $priceData->get($product->id) : null,
            );
        }
    }
}

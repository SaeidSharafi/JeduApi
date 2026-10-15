<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Product\ProductableEnum;
use App\Enums\Product\ProductSortFieldEnum;
use App\Enums\System\SearchEngine;
use App\Models\Product;
use App\Query\ProductListing;
use App\Query\ProductListingScope;
use Closure;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

final class ProductSearch
{
    public function __construct(
        private readonly SearchEngineService $engine,
        private readonly ?Closure $typesenseAvailability = null,
        private readonly ?Closure $scoutSearch = null,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function search(ProductListRequestData $requestData): LengthAwarePaginator
    {
        if ($this->isTypesenseAvailable()) {
            try {
                return $this->scoutSearch !== null
                    ? ($this->scoutSearch)($requestData)
                    : $this->searchScout($requestData);
            } catch (Exception $exception) {
                Log::warning('Typesense product search failed, falling back to database', [
                    'query' => $requestData->q,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $this->searchDatabase($requestData);
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function searchDatabase(ProductListRequestData $requestData): LengthAwarePaginator
    {
        $scope = ProductListingScope::forListing(
            $requestData->type !== null ? ProductableEnum::from($requestData->type) : null,
            $requestData->filter,
        );

        $query = $scope->applyToDatabase(Product::query());
        $query = ProductListing::forListing($query);

        if ($requestData->q) {
            $query->select('products.*')->selectScore(table: 'products');
            $query->search($requestData->q);
        }

        if ($requestData->sortBy === 'capacity_utilization') {
            return ProductListing::paginate(
                ProductListing::sortByCapacityUtilization($query, $scope->capacityThreshold()),
                $requestData->per_page,
            );
        }

        $isDefaultOrder = $requestData->sortBy === 'created_at' && $requestData->sortOrder === 'desc';

        if ($isDefaultOrder && $requestData->q) {
            $query->orderByScore();
        } elseif ($requestData->sortBy === 'price') {
            if (! $scope->joinsProductPrices()) {
                $query->addSelect('products.*')
                    ->join('product_prices', 'products.id', '=', 'product_prices.product_id');
            }

            $query->orderBy('product_prices.min_price', $requestData->sortOrder);
        } else {
            $query = ProductListing::sortBy($query, $requestData->sortBy, $requestData->sortOrder);
        }

        return ProductListing::paginate($query, $requestData->per_page);
    }

    /** @codeCoverageIgnore */
    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function searchScout(ProductListRequestData $requestData): LengthAwarePaginator
    {
        if (! $this->isTypesenseAvailable()) {
            return $this->searchDatabase($requestData);
        }

        $scope = ProductListingScope::forListing(
            $requestData->type ? ProductableEnum::from($requestData->type) : null,
            $requestData->filter,
        );

        $query = Product::scoutSearch($requestData->q ?: '*')
            ->options([
                'query_by'  => 'embedding',
                'prefix'    => 'false',
                'num_typos' => '0',
            ]);

        $scope->applyToScout($query);

        if (in_array($requestData->sortBy, ProductSortFieldEnum::ALLOWED, true)) {
            $requestData->sortBy === 'capacity_utilization'
                ? $query->orderByDesc('max_capacity_utilization')
                : $query->orderBy($requestData->sortBy, $requestData->sortOrder);
        }

        return $query
            ->query(ProductListing::forListing(...))
            ->paginate($requestData->per_page)
            ->withQueryString();
    }

    private function isTypesenseAvailable(): bool
    {
        if ($this->typesenseAvailability !== null) {
            return (bool) ($this->typesenseAvailability)();
        }

        return $this->engine->engine() === SearchEngine::TYPESENSE;
    }
}

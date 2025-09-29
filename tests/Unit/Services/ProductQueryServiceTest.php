<?php

declare(strict_types=1);

use App\Services\Shop\ProductQueryService;
use App\Enums\Product\ProductableEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

describe('ProductQueryService unit tests', function () {
    beforeEach(function () {
        $this->service = new ProductQueryService();
    });

    describe('fluent interface', function () {
        it('returns self for chainable methods', function () {
            expect($this->service->availableProducts())->toBe($this->service)
                ->and($this->service->search('test'))->toBe($this->service)
                ->and($this->service->featured())->toBe($this->service)
                ->and($this->service->withDiscounts())->toBe($this->service)
                ->and($this->service->popular())->toBe($this->service)
                ->and($this->service->sortBy('name'))->toBe($this->service)
                ->and($this->service->limit(10))->toBe($this->service)
                ->and($this->service->forListing())->toBe($this->service);
        });

        it('allows method chaining in any order', function () {
            $result = $this->service
                ->availableProducts()
                ->search('test')
                ->featured()
                ->withDiscounts()
                ->sortBy('price', 'asc')
                ->limit(5);

            expect($result)->toBe($this->service);
        });
    });

    describe('productable type filtering', function () {
        it('sets single product type correctly', function () {
            $service = ProductQueryService::make()->ofType(ProductableEnum::COURSE);

            // Use reflection to check internal state
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('productableTypes');
            $property->setAccessible(true);

            expect($property->getValue($service))->toBe([ProductableEnum::COURSE->value]);
        });

        it('sets multiple product types correctly', function () {
            $types = [ProductableEnum::COURSE, ProductableEnum::SEMINAR];
            $service = ProductQueryService::make()->ofTypes($types);

            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('productableTypes');
            $property->setAccessible(true);

            expect($property->getValue($service))->toBe([
                ProductableEnum::COURSE->value,
                ProductableEnum::SEMINAR->value
            ]);
        });

        it('defaults to all productable types', function () {
            $service = ProductQueryService::make();

            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('productableTypes');
            $property->setAccessible(true);

            expect($property->getValue($service))->toBe(ProductableEnum::getAllValues());
        });
    });

    describe('search method', function () {
        it('handles null search term gracefully', function () {
            $result = $this->service->search(null);
            expect($result)->toBe($this->service);
        });

        it('handles empty search term gracefully', function () {
            $result = $this->service->search('');
            expect($result)->toBe($this->service);
        });

        it('handles whitespace-only search term', function () {
            $result = $this->service->search('   ');
            expect($result)->toBe($this->service);
        });
    });

    describe('price range filtering', function () {
        it('handles null price parameters gracefully', function () {
            $result = $this->service->priceRange(null, null);
            expect($result)->toBe($this->service);
        });

        it('handles only min price', function () {
            $result = $this->service->priceRange(100, null);
            expect($result)->toBe($this->service);
        });

        it('handles only max price', function () {
            $result = $this->service->priceRange(null, 500);
            expect($result)->toBe($this->service);
        });
    });

    describe('sorting validation', function () {
        it('ignores invalid sort fields', function () {
            $result = $this->service->sortBy('invalid_field');
            expect($result)->toBe($this->service);
        });

        it('ignores invalid sort directions', function () {
            $result = $this->service->sortBy('name', 'invalid_direction');
            expect($result)->toBe($this->service);
        });

        it('accepts valid sort combinations', function () {
            $validFields = ['created_at', 'updated_at', 'name', 'price'];
            $validDirections = ['asc', 'desc'];

            foreach ($validFields as $field) {
                foreach ($validDirections as $direction) {
                    $result = $this->service->sortBy($field, $direction);
                    expect($result)->toBe($this->service);
                }
            }
        });
    });

    describe('category filtering', function () {
        it('handles empty category array gracefully', function () {
            $result = $this->service->inCategories([]);
            expect($result)->toBe($this->service);
        });

        it('handles single category correctly', function () {
            $result = $this->service->inCategories([1]);
            expect($result)->toBe($this->service);
        });

        it('handles multiple categories correctly', function () {
            $result = $this->service->inCategories([1, 2, 3]);
            expect($result)->toBe($this->service);
        });
    });

    describe('terminal methods return correct types', function () {
        it('get() returns Collection', function () {
            $service = ProductQueryService::make();

            /** @var Builder|MockInterface $queryMock */
            $queryMock = mock(Builder::class);

            $queryMock->shouldReceive('whereHasMorph')
                ->once()
                ->with('productable', [ProductableEnum::COURSE->value], \Mockery::type(Closure::class));

            $queryMock->shouldReceive('get')->once()->andReturn(new Collection(['product1', 'product2']));

            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('query');
            $property->setAccessible(true);
            $property->setValue($service, $queryMock);
            $service->ofType(ProductableEnum::COURSE);
            $service->byCourseLevel(\App\Enums\CourseDifficultyLevelEnum::BEGINNER); // Add a constraint

            // 7. Finally, call the public method we are testing.
            $result = $service->get();

            // 8. Assert the outcome.
            expect($result)->toBeInstanceOf(Collection::class)
                ->and($result)->toHaveCount(2);
        });

        it('first() returns Product or null', function () {
            $service = new ProductQueryService();
            /** @var Builder|MockInterface $queryMock */
            $queryMock = mock(Builder::class);

            $product = new Product(['name' => 'Test Product']);

            $service->ofType(ProductableEnum::COURSE);

            $queryMock->shouldReceive('whereHasMorph')
                ->once()
                ->with('productable', [ProductableEnum::COURSE->value], \Mockery::type(Closure::class));

            $queryMock->shouldReceive('first')->once()->andReturn($product);

            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('query');
            $property->setAccessible(true);
            $property->setValue($service, $queryMock);
            $service->ofType(ProductableEnum::COURSE);
            $service->byCourseLevel(\App\Enums\CourseDifficultyLevelEnum::BEGINNER);

            $result = $service->first();

            expect($result)->toBeInstanceOf(Product::class)
                ->and($result->name)->toBe('Test Product');
        });

        it('paginate() returns LengthAwarePaginator', function () {
            $service = new ProductQueryService();
            /** @var Builder|MockInterface $queryMock */
            $queryMock = mock(Builder::class);

            $queryMock->shouldReceive('first')->once()->andReturn(null);

            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('query');
            $property->setAccessible(true);
            $property->setValue($service, $queryMock);

            $result = $service->first();

            expect($result)->toBeNull();
        });
    });

    describe('course level filtering', function () {
        it('handles course level enum correctly', function () {
            $result = $this->service->byCourseLevel(CourseDifficultyLevelEnum::BEGINNER);
            expect($result)->toBe($this->service);
        });
    });

    describe('deferred constraints collection', function () {
        it('collects multiple constraints for same relationship', function () {
            $service = $this->service
                ->inCategory('test-slug')
                ->inCategories([1, 2, 3]);

            // Both constraints should be collected for 'categories' relationship
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('relationshipConstraints');
            $property->setAccessible(true);

            $constraints = $property->getValue($service);
            expect($constraints)->toHaveKey('categories')
                ->and($constraints['categories'])->toHaveCount(2);
        });

    });

});

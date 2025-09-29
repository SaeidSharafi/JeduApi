<?php

declare(strict_types=1);

namespace Tests\Unit\Data\Shop\Product\Course;

use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Product\ProductableEnum;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;

// We group all tests for this Data object in a "describe" block.
describe('ProductListRequestData Validation Rules', function () {

    // Test Case 1: Testing all failure scenarios using a dataset.
    // The `with()` function provides the data for each test run.
    it('fails validation for invalid data', function (array $data, string $expectedFailedField) {
        // 1. Get the combined rules from the static method.
        $rules = ProductListRequestData::rules();

        // 2. Create a Laravel validator instance with the invalid data.
        $validator = Validator::make($data, $rules);

        // 3. Assert that validation fails and the specific field has an error message.
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has($expectedFailedField))->toBeTrue();

    })->with([
        // --- Dataset for rules in ProductListRequestData ---
        'invalid sortBy'             => [['sortBy' => 'invalid_column'], 'sortBy'],
        'invalid sortOrder'          => [['sortOrder' => 'sideways'], 'sortOrder'],
        'page is not an integer'     => [['page' => 'one'], 'page'],
        'page is less than 1'        => [['page' => 0], 'page'],
        'per_page is not an integer' => [['per_page' => 'ten'], 'per_page'],
        'per_page is more than 100'  => [['per_page' => 101], 'per_page'],

        // --- Dataset for dynamically prefixed rules from ProductFilterData ---
        'filter search is not a string'           => [['filter' => ['search' => 123]], 'filter.search'],
        'filter category_ids is not an array'     => [['filter' => ['category_ids' => '1,2']], 'filter.category_ids'],
        'filter type has invalid enum value'      => [['filter' => ['type' => 'invalid-type']], 'filter.type'],
        'filter min_price is negative'            => [['filter' => ['min_price' => -10]], 'filter.min_price'],
        'filter max_price is less than min_price' => [['filter' => ['min_price' => 100, 'max_price' => 50]], 'filter.max_price'],
        'filter with_discounts is not boolean'    => [['filter' => ['with_discounts' => 'yes']], 'filter.with_discounts'],
    ]);

    it('passes validation with valid data', function () {
        $category  = Category::factory()->create();
        $validData = [
            'filter' => [
                'search'         => 'My Awesome Product',
                'category_ids'   => [$category->id],
                'type'           => ProductableEnum::COURSE->value,
                'min_price'      => 100,
                'max_price'      => 200,
                'with_discounts' => true,
            ],
            'sortBy'    => 'name',
            'sortOrder' => 'asc',
            'page'      => 2,
            'per_page'  => 50,
        ];

        $rules     = ProductListRequestData::rules();
        $validator = Validator::make($validData, $rules);

        expect($validator->fails())->toBeFalse();
    });
});

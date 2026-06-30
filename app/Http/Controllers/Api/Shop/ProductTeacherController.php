<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Data\Shop\Teacher\TeacherDetailData;
use App\Http\Controllers\Controller;
use App\Models\Product;

/**
 * @group Shop - Teacher
 *
 * APIs for viewing teachers associated with a product.
 */
final class ProductTeacherController extends Controller
{
    /**
     * Retrieve the list of teachers associated with a specific product.
     *
     * This is called when viewing a product's details to show its teachers.
     *
     * @urlParam product_slug string required The slug of the product. Example: advanced-javascript-course
     *
     * @responseFile resources/responses/shop/products/teachers.json
     */
    public function __invoke(Product $product)
    {

        $product->load('productDeliveryOptions.teachers');
        $teachers = [];
        $product->productDeliveryOptions->each(function ($deliveryOption) use (&$teachers): void {
            $deliveryOption->teachers->each(function ($teacher) use (&$teachers): void {
                $teachers[$teacher->id] = $teacher;
            });

        });

        return apiResponse()->success(TeacherDetailData::collect(array_values($teachers)));
    }
}

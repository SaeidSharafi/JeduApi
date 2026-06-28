<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Category\CategoryController;
use App\Http\Controllers\Api\Admin\Category\CategoryItemsController;
use App\Http\Controllers\Api\Admin\Content\GoodForStartController;
use App\Http\Controllers\Api\Admin\Product\ArchiveProductController;
use App\Http\Controllers\Api\Admin\Product\CourseController;
use App\Http\Controllers\Api\Admin\Product\DigitalAssetController;
use App\Http\Controllers\Api\Admin\Product\ProductController;
use App\Http\Controllers\Api\Admin\Product\ProductDeliveryOptionController;
use App\Http\Controllers\Api\Admin\Product\RelatedProductController;
use App\Http\Controllers\Api\Admin\Product\SeminarController;

// Product Management and Categories
Route::apiResource('categories', CategoryController::class);

// GoodForStart endpoints for category items
Route::prefix('categories/{category}')->name('categories.')->group(function (): void {
    Route::get('items', CategoryItemsController::class)->name('items.index');
    Route::post('good-for-start', GoodForStartController::class)
        ->name('good-for-start.set');
});

Route::apiResource('courses', CourseController::class);
Route::apiResource('digital-assets', DigitalAssetController::class);
Route::apiResource('seminars', SeminarController::class);

Route::apiResource('products', ProductController::class);
Route::post('products/{product}/archive', ArchiveProductController::class)->name('products.archive');
Route::apiResource('products/{product}/delivery-options', ProductDeliveryOptionController::class);

// Related Products Management
Route::prefix('products/{product}/related-products')->name('products.related-products.')->group(function (): void {
    Route::get('/', [RelatedProductController::class, 'index'])->name('index');
    Route::post('/', [RelatedProductController::class, 'store'])->name('store');
    Route::delete('/{relatedProduct}', [RelatedProductController::class, 'destroy'])->name('destroy');
});

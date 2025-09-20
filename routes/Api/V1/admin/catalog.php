<?php
use App\Http\Controllers\Api\Admin\Category\CategoryController;
use App\Http\Controllers\Api\Admin\Category\CategoryItemsController;
use App\Http\Controllers\Api\Admin\Category\GoodForStartController;
use App\Http\Controllers\Api\Admin\Product\ArchiveProductController;
use App\Http\Controllers\Api\Admin\Product\CourseController;
use App\Http\Controllers\Api\Admin\Product\DigitalAssetController;
use App\Http\Controllers\Api\Admin\Product\ProductController;
use App\Http\Controllers\Api\Admin\Product\ProductDeliveryOptionController;
use App\Http\Controllers\Api\Admin\Product\SeminarController;

// Product Management and Categories
Route::apiResource('category', CategoryController::class);

// GoodForStart endpoints for category items
Route::prefix('category/{category}')->name('category.')->group(function (): void {
    Route::get('items', CategoryItemsController::class)->name('items.index');
    Route::post('good-for-start',
        [GoodForStartController::class, 'set'])
        ->name('good-for-start.set');
});

Route::apiResource('course', CourseController::class);
Route::apiResource('digital-asset', DigitalAssetController::class);
Route::apiResource('seminar', SeminarController::class);

Route::apiResource('product', ProductController::class);
Route::post('product/{product}/archive', ArchiveProductController::class)->name('product.archive');
Route::apiResource('product/{product}/delivery-option', ProductDeliveryOptionController::class);

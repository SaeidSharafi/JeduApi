<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\Product\ArchiveProductController;
use App\Http\Controllers\Api\Admin\Product\CourseController;
use App\Http\Controllers\Api\Admin\Product\DigitalAssetController;
use App\Http\Controllers\Api\Admin\Product\ProductController;
use App\Http\Controllers\Api\Admin\Product\ProductDeliveryOptionController;
use App\Http\Controllers\Api\Admin\Product\SeminarController;

Route::middleware(['auth:staff', 'admin.audit'])->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {

        Route::resource('course', CourseController::class)
            ->except(['edit', 'create']);

        Route::resource('digital-asset', DigitalAssetController::class)
            ->except(['edit', 'create']);

        Route::resource('seminar', SeminarController::class)
            ->except(['edit', 'create']);

        Route::resource('category', CategoryController::class)
            ->except(['edit', 'create']);

        Route::resource('product', ProductController::class)
            ->except(['edit', 'create']);
        Route::post('product/{product}/archive', ArchiveProductController::class)
            ->name('product.archive');

        Route::resource('product/{product}/delivery-option', ProductDeliveryOptionController::class)
            ->except(['edit', 'create']);
    });
});

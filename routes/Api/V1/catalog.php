<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\DigitalAssetController;

Route::middleware('auth:admin')->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {

        Route::resource('course', App\Http\Controllers\Api\Admin\CourseController::class)
            ->except(['edit', 'create']);

        Route::resource('digital-asset', DigitalAssetController::class)
            ->except(['edit', 'create']);

        Route::resource('category', CategoryController::class)
            ->except(['edit', 'create']);
    });
});

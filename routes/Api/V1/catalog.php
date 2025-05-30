<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\Admin\DigitalAssetController;
use App\Http\Controllers\SeminarController;

Route::middleware('auth:admin')->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {

        Route::resource('course', CourseController::class)
            ->except(['edit', 'create']);

        Route::resource('digital-asset', DigitalAssetController::class)
            ->except(['edit', 'create']);

        Route::resource('seminar', SeminarController::class)
            ->except(['edit', 'create']);

        Route::resource('category', CategoryController::class)
            ->except(['edit', 'create']);
    });
});

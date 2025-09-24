<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Blog\BlogCategoryController;
use App\Http\Controllers\Api\Admin\Blog\BlogPostController;
use Illuminate\Support\Facades\Route;

Route::prefix('blog')->name('blog.')->group(function (): void {
    Route::apiResource('category', BlogCategoryController::class);
    Route::apiResource('post', BlogPostController::class);
});
// Blog Category CRUD

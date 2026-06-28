<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Blog\BlogCategoryController;
use App\Http\Controllers\Api\Admin\Blog\BlogPostController;
use Illuminate\Support\Facades\Route;

Route::prefix('blog')->name('blog.')->group(function (): void {
    Route::apiResource('categories', BlogCategoryController::class);
    Route::apiResource('posts', BlogPostController::class);
});
// Blog Category CRUD

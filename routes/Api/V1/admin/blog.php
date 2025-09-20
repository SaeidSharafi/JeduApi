<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Blog\BlogCategoryController;
use App\Http\Controllers\Api\Admin\Blog\BlogPostController;

Route::prefix('blog')->name('blog.')->group(function (): void {
    Route::apiResource('category', BlogCategoryController::class);
    Route::apiResource('post', BlogPostController::class);
});
// Blog Category CRUD


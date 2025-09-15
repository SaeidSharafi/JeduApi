<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Blog\BlogCategoryController;
use App\Http\Controllers\Api\Admin\Blog\BlogPostController;

Route::prefix('blog')->name('blog.')->group(function () {
    // Blog Category CRUD
    Route::get('category', [BlogCategoryController::class, 'index'])->name('category.index');
    Route::post('category', [BlogCategoryController::class, 'store'])->name('category.store');
    Route::get('category/{category}', [BlogCategoryController::class, 'show'])->name('category.show');
    Route::put('category/{category}', [BlogCategoryController::class, 'update'])->name('category.update');
    Route::delete('category/{category}', [BlogCategoryController::class, 'destroy'])->name('category.destroy');

    // Blog Post CRUD
    Route::get('posts', [BlogPostController::class, 'index'])->name('posts.index');
    Route::post('posts', [BlogPostController::class, 'store'])->name('posts.store');
    Route::get('posts/{post}', [BlogPostController::class, 'show'])->name('posts.show');
    Route::put('posts/{post}', [BlogPostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [BlogPostController::class, 'destroy'])->name('posts.destroy');
});
// Blog Category CRUD


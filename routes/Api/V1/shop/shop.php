<?php

declare(strict_types=1);

use App\Enums\ProductableEnum;
use App\Http\Controllers\Api\Shop\HomePageContentController;

// Home Page Content
Route::get('home-page-content', HomePageContentController::class)->name('home-page-content');

// temp shop routes returning empty json
    Route::get('categories', function () {
        return response()->json(\App\Models\Category::paginate());
    })->name('categories');

//courses
    Route::get('courses', function () {
        return response()->json(
            \App\Models\Product::query()
                ->where('productable_type', ProductableEnum::COURSE)
                ->active()
                ->paginate()
        );
    })->name('courses.index');
    //course by slug
    Route::get('courses/slug/{slug}', function () {
        return response()->json(
            \App\Models\Product::query()
                ->where('productable_type', ProductableEnum::COURSE)
                ->where('slug', request()->route('slug'))
                ->active()
                ->firstOrFail()
        );
    })->name('courses.show');

   //semianar
    Route::get('seminars', function () {
        return response()->json(
            \App\Models\Product::query()
                ->where('productable_type', ProductableEnum::SEMINAR)
                ->active()
                ->paginate()
        );
    })->name('seminars.index');
    //seminar by slug
    Route::get('seminars/slug/{slug}', function () {
        return response()->json(
            \App\Models\Product::query()
                ->where('productable_type', ProductableEnum::SEMINAR)
                ->where('slug', request()->route('slug'))
                ->active()
                ->firstOrFail()
        );
    })->name('seminars.show');

    // digitla files
    Route::get('digital-assets', function () {
        return response()->json(
            \App\Models\Product::query()
                ->where('productable_type', ProductableEnum::DIGITAL_FILE)
                ->active()
                ->paginate()
        );
    })->name('digital-assets.index');
    //digital file by slug
    Route::get('digital-asset/slug/{slug}', function () {
        return response()->json(
            \App\Models\Product::query()
                ->where('productable_type', ProductableEnum::DIGITAL_ASSET)
                ->where('slug', request()->route('slug'))
                ->active()
                ->firstOrFail()
        );
    })->name('digital-assets.show');

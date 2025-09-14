<?php

declare(strict_types=1);

Route::middleware('auth:user')->name('shop.')->group(function () {
    Route::singleton('profile', App\Http\Controllers\Api\Shop\ProfileController::class)
        ->only(['show', 'update']);

    Route::prefix('my-courses')->name('my-courses.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Shop\MyCourses\EnrolmentController::class, 'index'])
            ->name('index');

        Route::get('/{enrolment:uuid}', [App\Http\Controllers\Api\Shop\MyCourses\EnrolmentController::class, 'show'])
            ->name('show');
    });
});

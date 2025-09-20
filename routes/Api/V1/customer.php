<?php

declare(strict_types=1);

Route::middleware(['auth:user'])
    ->prefix('shop')
    ->name('shop.')
    ->group(function () {
        Route::singleton('profile', App\Http\Controllers\Api\Shop\ProfileController::class)
            ->only(['show', 'update']);

        Route::prefix('my-courses')->name('my-courses.')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Shop\MyCourses\EnrollmentController::class, 'index'])
                ->name('index');

            Route::get('/{enrollment:uuid}',
                [App\Http\Controllers\Api\Shop\MyCourses\EnrollmentController::class, 'show'])
                ->name('show');
        });
    });


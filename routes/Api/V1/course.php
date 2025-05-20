<?php

declare(strict_types=1);

Route::middleware('auth:admin')->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::resource('course', App\Http\Controllers\Api\Admin\CourseController::class)
            ->except(['edit', 'create']);
    });
});

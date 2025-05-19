<?php

use App\Http\Controllers\Api\Admin\MediaController;

Route::middleware('auth:admin')->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::post('media/upload', [MediaController::class, 'upload'])->name('media.upload');
    });
});

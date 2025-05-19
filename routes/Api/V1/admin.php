<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\UploadMediaController;
use App\Http\Controllers\Api\Admin\ViewMediaController;

Route::middleware('auth:admin')->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::post('media/upload', UploadMediaController::class)->name('media.upload');
        Route::get('media/{media}', ViewMediaController::class)->name('media.view');
    });
});

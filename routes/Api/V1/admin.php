<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\PrivateFileDownloadController;
use App\Http\Controllers\Api\Admin\UploadMediaController;
use App\Http\Controllers\Api\Admin\UploadPrivateController;
use App\Http\Controllers\Api\Admin\ViewMediaController;
use App\Http\Controllers\Api\Admin\ViewPrivateFileController;

Route::middleware('auth:staff')->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::resource('staff', App\Http\Controllers\Api\Admin\StaffController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::resource('role', App\Http\Controllers\Api\Admin\RoleController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::get('permission', App\Http\Controllers\Api\Admin\PermissonController::class)
            ->name('permission.index');
        Route::resource('vendor', App\Http\Controllers\Api\Admin\VendorController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::resource('teacher', App\Http\Controllers\Api\Admin\TeacherController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::post('media/upload', UploadMediaController::class)->name('media.upload');
        Route::get('media/{media}', ViewMediaController::class)->name('media.view');
        Route::post('private-file/upload', UploadPrivateController::class)
            ->name('private-upload.upload');
        Route::get('private-file/{file}', ViewPrivateFileController::class)
            ->name('private-upload.view');
        Route::get('private-file/{file}/download', PrivateFileDownloadController::class)
            ->name('private-upload.download');

    });
});

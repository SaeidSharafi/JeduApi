<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\FileManagement\PrivateFileDownloadController;
use App\Http\Controllers\Api\Admin\FileManagement\UploadMediaController;
use App\Http\Controllers\Api\Admin\FileManagement\UploadPrivateController;
use App\Http\Controllers\Api\Admin\FileManagement\ViewMediaController;
use App\Http\Controllers\Api\Admin\FileManagement\ViewPrivateFileController;

Route::post('media/upload', UploadMediaController::class)->name('media.upload');
Route::get('media/{media}', ViewMediaController::class)->name('media.view');
Route::post('private-file/upload', UploadPrivateController::class)
    ->name('private-upload.upload');
Route::get('private-file/{file}', ViewPrivateFileController::class)
    ->name('private-upload.view');
Route::get('private-file/{file}/download', PrivateFileDownloadController::class)
    ->name('private-upload.download');

<?php

declare(strict_types=1);

use App\Enums\ImportExport\SpreadsheetResourceEnum;
use App\Http\Controllers\Api\Admin\ImportExport\ApproveImportController;
use App\Http\Controllers\Api\Admin\ImportExport\CreateExportController;
use App\Http\Controllers\Api\Admin\ImportExport\DownloadExportController;
use App\Http\Controllers\Api\Admin\ImportExport\DownloadImportTemplateController;
use App\Http\Controllers\Api\Admin\ImportExport\PreviewImportController;
use App\Http\Controllers\Api\Admin\ImportExport\ShowImportRunController;
use Illuminate\Support\Facades\Route;

/*
 * Resource scoped import routes, registered only for resources the server side
 * registry knows: /admin/users/import and /admin/users/import/template.
 */
Route::whereIn('resource', SpreadsheetResourceEnum::cases())->group(function (): void {
    Route::get('{resource}/export', CreateExportController::class)->name('exports.create');
    Route::get('{resource}/export/{artifact}', DownloadExportController::class)
        ->whereUuid('artifact')->middleware('signed:relative')->name('exports.download');
    Route::get('{resource}/import/template', DownloadImportTemplateController::class)->name('imports.template');
    Route::post('{resource}/import', PreviewImportController::class)->name('imports.preview');
    Route::get('{resource}/import/{run}', ShowImportRunController::class)->name('imports.results');
    Route::post('{resource}/import/{run}/approve', ApproveImportController::class)->name('imports.approve');
});

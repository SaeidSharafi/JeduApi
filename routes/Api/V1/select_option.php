<?php

use App\Http\Controllers\Api\Admin\SelectOptions\CategorySelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\TermSelectOptionController;

Route::middleware('auth:staff')
    ->prefix('admin')->name('admin.')
    ->group(function (): void {
        Route::get('select-option/category', CategorySelectOptionController::class)
            ->name('select-option.category');
        Route::get('select-option/term', TermSelectOptionController::class)
            ->name('select-option.term');
});

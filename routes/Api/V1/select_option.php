<?php

use App\Http\Controllers\Api\Admin\SelectOptions\CategorySelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\TeacherSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\TermSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\VendorSelectOptionController;

Route::middleware('auth:staff')
    ->prefix('admin')->name('admin.')
    ->group(function (): void {
        Route::get('select-option/category', CategorySelectOptionController::class)
            ->name('select-option.category');
        Route::get('select-option/term', TermSelectOptionController::class)
            ->name('select-option.term');
        Route::get('select-option/vendor', VendorSelectOptionController::class)
            ->name('select-option.vendor');
        Route::get('select-option/teacher', TeacherSelectOptionController::class)
            ->name('select-option.teacher');
});

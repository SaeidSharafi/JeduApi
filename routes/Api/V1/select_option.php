<?php

use App\Http\Controllers\Api\Admin\SelectOptions\CategorySelectOptionController;

Route::middleware('auth:staff')
    ->prefix('admin')->name('admin.')
    ->group(function (): void {
        Route::get('select-option/category', CategorySelectOptionController::class)
            ->name('select-option.category');
});

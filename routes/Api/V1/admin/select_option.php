<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\SelectOptions\CategorySelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\ProductableSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\ProductSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\StaffSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\TeacherSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\TermSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\VendorSelectOptionController;

Route::get('select-option/category', CategorySelectOptionController::class)
    ->name('select-option.category');
Route::get('select-option/term', TermSelectOptionController::class)
    ->name('select-option.term');
Route::get('select-option/vendor', VendorSelectOptionController::class)
    ->name('select-option.vendor');
Route::get('select-option/teacher', TeacherSelectOptionController::class)
    ->name('select-option.teacher');
Route::get('select-option/productable', ProductableSelectOptionController::class)
    ->name('select-option.productable');
Route::get('select-option/staff', StaffSelectOptionController::class)
    ->name('select-option.staff');
Route::get('select-option/products/{productableType?}', ProductSelectOptionController::class)
    ->name('select-option.product');

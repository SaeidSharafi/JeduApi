<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\SelectOptions\CategorySelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\ProductableSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\ProductSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\StaffSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\TeacherSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\TermSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\VendorSelectOptionController;

Route::get('select-option/categories', CategorySelectOptionController::class)
    ->name('select-option.categories');
Route::get('select-option/terms', TermSelectOptionController::class)
    ->name('select-option.terms');
Route::get('select-option/vendors', VendorSelectOptionController::class)
    ->name('select-option.vendors');
Route::get('select-option/teachers', TeacherSelectOptionController::class)
    ->name('select-option.teacherss');
Route::get('select-option/productables', ProductableSelectOptionController::class)
    ->name('select-option.productables');
Route::get('select-option/staff', StaffSelectOptionController::class)
    ->name('select-option.staff');
Route::get('select-option/products/{productableType?}', ProductSelectOptionController::class)
    ->name('select-option.products');

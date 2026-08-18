<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\SelectOptions\BlogCategorySelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\CategorySelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\CustomerSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\FulfillmentDeliveryOptionsSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\ProductableSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\ProductSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\StaffSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\TeacherSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\TermSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\VendorSelectOptionController;
use App\Http\Controllers\Api\Admin\SelectOptions\WalletCampaignTypeSelectOptionController;

Route::get('select-option/categories', CategorySelectOptionController::class)
    ->name('select-option.categories');
Route::get('select-option/blog-categories', BlogCategorySelectOptionController::class)
    ->name('select-option.blog-categories');
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
Route::get('select-option/customers', CustomerSelectOptionController::class)
    ->name('select-option.customers');
Route::get('select-option/delivery-options', FulfillmentDeliveryOptionsSelectOptionController::class)
    ->name('select-option.delivery-options');
Route::get('select-option/products/{productableType?}', ProductSelectOptionController::class)
    ->name('select-option.products');
Route::get('select-option/wallet-campaign-types', WalletCampaignTypeSelectOptionController::class)
    ->name('select-option.wallet-campaign-types');

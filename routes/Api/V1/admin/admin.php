<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Forms\AdviceRequest\AdviceRequestController;
use App\Http\Controllers\Api\Admin\Forms\AdviceRequest\AdviceRequestUpdateStatusController;
use App\Http\Controllers\Api\Admin\Profile\StaffChangePasswordController;
use App\Http\Controllers\Api\Admin\Profile\StaffProfileController;
use App\Http\Controllers\Api\Admin\Review\ApproveReviewController;
use App\Http\Controllers\Api\Admin\Review\RejectReviewController;
use App\Http\Controllers\Api\Admin\Review\ReviewController;
use App\Http\Controllers\Api\Admin\Review\UpdateReviewFeaturedStatusController;
use App\Http\Controllers\Api\Admin\System\PermissionController;
use App\Http\Controllers\Api\Admin\System\RoleController;
use App\Http\Controllers\Api\Admin\TermController;
use App\Http\Controllers\Api\Admin\User\BanStaffController;
use App\Http\Controllers\Api\Admin\User\BanUserController;
use App\Http\Controllers\Api\Admin\User\StaffController;
use App\Http\Controllers\Api\Admin\User\TeacherController;
use App\Http\Controllers\Api\Admin\User\UnbanStaffController;
use App\Http\Controllers\Api\Admin\User\UnbanUserController;
use App\Http\Controllers\Api\Admin\User\UserController;
use App\Http\Controllers\Api\Admin\VendorController;
use App\Http\Controllers\Api\Admin\Wallet\AdjustWalletController;
use App\Http\Controllers\Api\Admin\Wallet\AdminWalletController;
use App\Http\Controllers\Api\Admin\Wallet\DepositToWalletController;
use App\Http\Controllers\Api\Admin\Wallet\WithdrawFromWalletController;

require __DIR__.'/blog.php';
require __DIR__.'/catalog.php';
require __DIR__.'/select_option.php';
require __DIR__.'/file.php';
require __DIR__.'/sale.php';
require __DIR__.'/setting.php';
require __DIR__.'/wallet.php';

Route::apiResource('staff', StaffController::class);
Route::prefix('staff/{staff}')->name('staff.')->group(function (): void {
    Route::post('ban', BanStaffController::class)->name('ban');
    Route::post('unban', UnbanStaffController::class)->name('unban');
});
Route::apiResource('roles', RoleController::class);
Route::get('permissions', PermissionController::class)->name('permissions.index');

Route::apiResource('vendors', VendorController::class);
Route::apiResource('teachers', TeacherController::class);
Route::apiResource('terms', TermController::class);
Route::apiResource('users', UserController::class);

Route::prefix('users/{user}')->name('users.')->group(function (): void {
    Route::apiSingleton('wallet', AdminWalletController::class)->creatable()->only('show', 'store');
    Route::prefix('wallet')->name('wallet.')->group(function (): void {
        Route::post('deposit', DepositToWalletController::class)->name('deposit');
        Route::post('withdrawal', WithdrawFromWalletController::class)->name('withdrawal');
        Route::post('adjustment', AdjustWalletController::class)->name('adjustment');
    });
    Route::post('ban', BanUserController::class)->name('ban');
    Route::post('unban', UnbanUserController::class)->name('unban');
});
Route::apiResource('reviews', ReviewController::class)
    ->except(['store', 'update']);
Route::post('reviews/{review}/approve', ApproveReviewController::class)->name('reviews.approve');
Route::post('reviews/{review}/reject', RejectReviewController::class)->name('reviews.reject');
Route::patch('reviews/{review}/featured', UpdateReviewFeaturedStatusController::class)
    ->name('reviews.update-featured-status');

// Advice Request Management
Route::apiResource('advice-requests', AdviceRequestController::class)->except(['store']);
Route::patch('advice-requests/{adviceRequest}/status', AdviceRequestUpdateStatusController::class)
    ->name('advice-requests.update-status');

Route::singleton('profile', StaffProfileController::class)
    ->only(['show', 'update']);
Route::put('change-password', StaffChangePasswordController::class)->name('change-password');

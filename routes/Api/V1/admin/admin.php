<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\AdviceRequest\AdviceRequestController;
use App\Http\Controllers\Api\Admin\AdviceRequest\AdviceRequestUpdateStatusController;

use App\Http\Controllers\Api\Admin\PermissonController;
use App\Http\Controllers\Api\Admin\Review\ApproveReviewController;
use App\Http\Controllers\Api\Admin\Review\RejectReviewController;
use App\Http\Controllers\Api\Admin\Review\ReviewController;
use App\Http\Controllers\Api\Admin\Review\UpdateReviewFeaturedStatusController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\StaffController;
use App\Http\Controllers\Api\Admin\TeacherController;
use App\Http\Controllers\Api\Admin\TermController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VendorController;


require __DIR__.'/blog.php';
require __DIR__.'/catalog.php';
require __DIR__.'/select_option.php';
require __DIR__.'/file.php';
require __DIR__.'/sale.php';
require __DIR__.'/setting.php';
require __DIR__.'/wallet.php';

Route::apiResource('staff', StaffController::class);
Route::apiResource('role', RoleController::class);
Route::get('permission', PermissonController::class)->name('permission.index');

Route::apiResource('vendor', VendorController::class);
Route::apiResource('teacher', TeacherController::class);
Route::apiResource('term', TermController::class);

Route::apiResource('user', UserController::class);

Route::apiResource('review', ReviewController::class)
    ->except(['store', 'update']);
Route::post('review/{review}/approve', ApproveReviewController::class)->name('review.approve');
Route::post('review/{review}/reject', RejectReviewController::class)->name('review.reject');
Route::patch('review/{review}/featured', UpdateReviewFeaturedStatusController::class)
    ->name('review.update-featured-status');

// Advice Request Management
Route::apiResource('advice-request', AdviceRequestController::class)->except(['store']);
Route::patch('advice-request/{adviceRequest}/status', AdviceRequestUpdateStatusController::class)
    ->name('advice-request.update-status');

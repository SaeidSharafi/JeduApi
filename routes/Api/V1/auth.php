<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Auth\StaffForgotPasswordController;
use App\Http\Controllers\Api\Admin\Auth\StaffInitiateAuthController;
use App\Http\Controllers\Api\Admin\Auth\StaffLogoutController;
use App\Http\Controllers\Api\Admin\Auth\StaffOtpAuthenticationController;
use App\Http\Controllers\Api\Admin\Auth\StaffPasswordLoginController;
use App\Http\Controllers\Api\Admin\Auth\StaffResendOtpController;
use App\Http\Controllers\Api\Admin\Auth\StaffResetPasswordController;
use App\Http\Controllers\Api\Shop\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Shop\Auth\InitiateAuthController;
use App\Http\Controllers\Api\Shop\Auth\LogoutController;
use App\Http\Controllers\Api\Shop\Auth\OtpAuthenticationController;
use App\Http\Controllers\Api\Shop\Auth\PasswordLoginController;
use App\Http\Controllers\Api\Shop\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Shop\Auth\ResnedOtpController;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('initiate', InitiateAuthController::class)->name('initiate');
    Route::post('login/password', PasswordLoginController::class)->name('password-login');
    Route::post('otp/verify', OtpAuthenticationController::class)->name('otp-verify');
    Route::post('otp/resend', ResnedOtpController::class)->name('otp-resend');
    Route::post('password/reset', ForgotPasswordController::class)->name('forgot-password');
    Route::post('password/reset/otp', ResetPasswordController::class)->name('password-reset');
    Route::post('logout', LogoutController::class)->middleware('auth:user')->name('logout');
});

// Admin Auth Routes
Route::prefix('admin/auth')->name('admin.auth.')->group(function (): void {
    Route::post('initiate', StaffInitiateAuthController::class)->name('initiate');
    Route::post('login/password', StaffPasswordLoginController::class)->name('password-login');
    Route::post('otp/verify', StaffOtpAuthenticationController::class)->name('otp-verify');
    Route::post('otp/resend', StaffResendOtpController::class)->name('otp-resend');
    Route::post('password/reset', StaffForgotPasswordController::class)->name('forgot-password');
    Route::post('password/reset/otp', StaffResetPasswordController::class)->name('password-reset');
    Route::post('logout', StaffLogoutController::class)->middleware('auth:staff')->name('logout');
});

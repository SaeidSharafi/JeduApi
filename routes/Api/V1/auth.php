<?php

use App\Http\Controllers\Api\V1\Admin\Auth\AdminForgotPasswordController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminInitiateAuthController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminLogoutController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminOtpAuthenticationController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminPasswordLoginController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminResendOtpController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\InitiateAuthController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\OtpAuthenticationController;
use App\Http\Controllers\Api\V1\Auth\PasswordLoginController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\ResnedOtpController;

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
    Route::post('initiate', AdminInitiateAuthController::class)->name('initiate');
    Route::post('login/password', AdminPasswordLoginController::class)->name('password-login');
    Route::post('otp/verify', AdminOtpAuthenticationController::class)->name('otp-verify');
    Route::post('otp/resend', AdminResendOtpController::class)->name('otp-resend');
    Route::post('password/reset', AdminForgotPasswordController::class)->name('forgot-password');
    Route::post('password/reset/otp', AdminResetPasswordController::class)->name('password-reset');
    Route::post('logout', AdminLogoutController::class)->middleware('auth:admin')->name('logout');
});

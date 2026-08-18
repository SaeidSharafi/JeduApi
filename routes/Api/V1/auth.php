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
use App\Http\Controllers\Api\Shop\Auth\ResendOtpController;
use App\Http\Controllers\Api\Shop\Auth\ResetPasswordController;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('initiate', InitiateAuthController::class)
        ->middleware('throttle:otp-initiate')
        ->name('initiate');
    Route::post('login/password', PasswordLoginController::class)
        ->middleware('throttle.password-login:shop')
        ->name('password-login');
    Route::post('otp/verify', OtpAuthenticationController::class)
        ->middleware('throttle:otp-verify')
        ->name('otp-verify');
    Route::post('otp/resend', ResendOtpController::class)
        ->middleware('throttle:otp-resend')
        ->name('otp-resend');
    Route::post('password/reset', ForgotPasswordController::class)
        ->middleware('throttle:otp-resend')
        ->name('forgot-password');
    Route::post('password/reset/otp', ResetPasswordController::class)
        ->middleware('throttle:otp-verify')
        ->name('password-reset');
    Route::post('logout', LogoutController::class)->middleware(['auth.cookie:user', 'auth:user'])->name('logout');
});

// Admin Auth Routes
Route::prefix('admin/auth')->name('admin.auth.')->group(function (): void {
    Route::post('initiate', StaffInitiateAuthController::class)
        ->middleware('throttle:otp-initiate')
        ->name('initiate');
    Route::post('login/password', StaffPasswordLoginController::class)
        ->middleware('throttle.password-login:staff')
        ->name('password-login');
    Route::post('otp/verify', StaffOtpAuthenticationController::class)
        ->middleware('throttle:otp-verify')
        ->name('otp-verify');
    Route::post('otp/resend', StaffResendOtpController::class)
        ->middleware('throttle:otp-resend')
        ->name('otp-resend');
    Route::post('password/reset', StaffForgotPasswordController::class)
        ->middleware('throttle:otp-resend')
        ->name('forgot-password');
    Route::post('password/reset/otp', StaffResetPasswordController::class)
        ->middleware('throttle:otp-verify')
        ->name('password-reset');
    Route::post('logout', StaffLogoutController::class)->middleware(['auth.cookie:staff', 'auth:staff'])->name('logout');
});

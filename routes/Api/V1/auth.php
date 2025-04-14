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

Route::prefix('auth')->group(function (): void {
    Route::post('initiate', InitiateAuthController::class);
    Route::post('login/password', PasswordLoginController::class);
    Route::post('otp/verify', OtpAuthenticationController::class);
    Route::post('otp/resend', ResnedOtpController::class);
    Route::post('password/reset', ForgotPasswordController::class);
    Route::post('password/reset/otp', ResetPasswordController::class);
    Route::post('logout', LogoutController::class)->middleware('auth:user');
});

// Admin Auth Routes
Route::prefix('admin/auth')->group(function (): void {
    Route::post('initiate', AdminInitiateAuthController::class);
    Route::post('login/password', AdminPasswordLoginController::class);
    Route::post('otp/request', AdminResendOtpController::class);
    Route::post('otp/verify', AdminOtpAuthenticationController::class);
    Route::post('password/reset', AdminForgotPasswordController::class);
    Route::post('password/reset/otp', AdminResetPasswordController::class);
    Route::post('logout', AdminLogoutController::class)->middleware('auth:admin');
});

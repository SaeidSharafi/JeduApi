<?php

use App\Http\Controllers\Api\V1\Auth\InitiateAuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordLoginController;
use App\Http\Controllers\Api\V1\Auth\OtpVerificationController;
use App\Http\Controllers\Api\V1\Auth\RequestOtpController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Admin\Auth\InitiateAuthController as AdminInitiateAuthController;
use App\Http\Controllers\Api\V1\Admin\Auth\PasswordLoginController as AdminPasswordLoginController;
use App\Http\Controllers\Api\V1\Admin\Auth\OtpVerificationController as AdminOtpVerificationController;
use App\Http\Controllers\Api\V1\Admin\Auth\RequestOtpController as AdminRequestOtpController;
use App\Http\Controllers\Api\V1\Admin\Auth\ResetPasswordController as AdminResetPasswordController;
use App\Http\Controllers\Api\V1\Admin\Auth\LogoutController as AdminLogoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Customer Auth Routes
    Route::prefix('auth')->group(function () {
        Route::post('initiate', InitiateAuthController::class);
        Route::post('login/password', PasswordLoginController::class);
        Route::post('otp/request', RequestOtpController::class);
        Route::post('otp/verify', OtpVerificationController::class);
        Route::post('password/reset/otp', ResetPasswordController::class);
        Route::post('logout', LogoutController::class)->middleware('auth:sanctum');
    });

    // Admin Auth Routes
    Route::prefix('admin/auth')->group(function () {
        Route::post('initiate', AdminInitiateAuthController::class);
        Route::post('login/password', AdminPasswordLoginController::class);
        Route::post('otp/request', AdminRequestOtpController::class);
        Route::post('otp/verify', AdminOtpVerificationController::class);
        Route::post('password/reset/otp', AdminResetPasswordController::class);
        Route::post('logout', AdminLogoutController::class)->middleware('auth:admin');
    });
});

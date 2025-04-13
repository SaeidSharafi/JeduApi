<?php

use App\Http\Controllers\Api\V1\Auth\InitiateAuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordLoginController;
use App\Http\Controllers\Api\V1\Auth\RequestOtpController;
use App\Http\Controllers\Api\V1\Auth\OtpVerificationController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminInitiateAuthController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminPasswordLoginController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminRequestOtpController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminOtpVerificationController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminResetPasswordController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminLogoutController;


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

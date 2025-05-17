<?php

use App\Http\Controllers\Api\Admin\Auth\AdminForgotPasswordController;
use App\Http\Controllers\Api\Admin\Auth\AdminInitiateAuthController;
use App\Http\Controllers\Api\Admin\Auth\AdminLogoutController;
use App\Http\Controllers\Api\Admin\Auth\AdminOtpAuthenticationController;
use App\Http\Controllers\Api\Admin\Auth\AdminPasswordLoginController;
use App\Http\Controllers\Api\Admin\Auth\AdminResendOtpController;
use App\Http\Controllers\Api\Admin\Auth\AdminResetPasswordController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\InitiateAuthController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\OtpAuthenticationController;
use App\Http\Controllers\Api\Auth\PasswordLoginController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\ResnedOtpController;

Route::middleware('auth:admin')->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::resource('course', \App\Http\Controllers\Api\Admin\CourseController::class)
        ;
    });
});


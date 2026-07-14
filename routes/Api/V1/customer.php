<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Shop\AvatarController;
use App\Http\Controllers\Api\Shop\ProfileController;
use App\Http\Controllers\Api\Shop\Student\CancelOrderController;
use App\Http\Controllers\Api\Shop\Student\DigitalAssetDownloadController;
use App\Http\Controllers\Api\Shop\Student\DigitalAssetEnrollmentController;
use App\Http\Controllers\Api\Shop\Student\EnrollmentController;
use App\Http\Controllers\Api\Shop\Student\JoinUrlController;
use App\Http\Controllers\Api\Shop\Student\MoodleSsoController;
use App\Http\Controllers\Api\Shop\Student\OrderController;
use App\Http\Controllers\Api\Shop\Student\QuizController;
use App\Http\Controllers\Api\Shop\Student\RetryPaymentController;
use App\Http\Controllers\Api\Shop\Student\ShowPaymentController;
use App\Http\Controllers\Api\Shop\Wallet\WalletTopupController;

Route::middleware(['auth:user'])
    ->prefix('shop')
    ->name('shop.')
    ->group(function (): void {

        // Profile remains top-level as it applies to any authenticated user
        Route::singleton('profile', ProfileController::class)
            ->only(['show', 'update']);
        Route::post('customer/avatar', [AvatarController::class, 'update'])->name('profile.avatar.update');
        Route::delete('customer/avatar', [AvatarController::class, 'destroy'])->name('profile.avatar.destroy');
        // ==========================================
        // 1. STUDENT DASHBOARD
        // ==========================================
        Route::prefix('student')->name('student.')->group(function (): void {

            // Enrolled Courses
            Route::prefix('courses')->name('courses.')->group(function (): void {
                Route::get('/', [EnrollmentController::class, 'index'])
                    ->name('index');

                Route::get('/{enrollment:uuid}', [EnrollmentController::class, 'show'])
                    ->name('show');

                Route::post('/{enrollment:uuid}/moodle/sso', MoodleSsoController::class)
                    ->name('moodle.sso');

                Route::get('/{enrollment:uuid}/join', JoinUrlController::class)
                    ->name('join');
            });

            Route::get('quizzes', QuizController::class)->name('quizzes');

            // Enrolled Digital Assets
            Route::prefix('digital-assets')->name('digital-assets.')->group(function (): void {
                Route::get('/', DigitalAssetEnrollmentController::class)
                    ->name('index');

                Route::get('/{enrollment:uuid}/download/{digitalAsset}', DigitalAssetDownloadController::class)
                    ->name('download');
            });

            // Orders & Payments
            Route::prefix('orders')->name('orders.')->group(function (): void {
                Route::get('/', [OrderController::class, 'index'])
                    ->name('index');

                Route::get('/{order:increment_id}', [OrderController::class, 'show'])
                    ->name('show');

                Route::post('/{order:increment_id}/cancel', CancelOrderController::class)
                    ->name('cancel');

                Route::post('/{increment_id}/retry-payment', RetryPaymentController::class)
                    ->middleware('throttle:10,1')
                    ->name('retry-payment');

            });
            Route::prefix('payments')->name('payments.')->group(function (): void {
                Route::get('/', [ShowPaymentController::class, 'index'])
                    ->name('index');
                Route::get('/{uuid}', [ShowPaymentController::class, 'show'])
                    ->name('show');
            });
        });

        // ==========================================
        // 2. TEACHER DASHBOARD
        // ==========================================
        Route::prefix('teacher')->name('teacher.')->group(function (): void {
            // Examples of future teacher-specific endpoints:
            // Route::get('/courses', [TaughtCourseController::class, 'index'])->name('courses.index');
            // Route::get('/students', [StudentListController::class, 'index'])->name('students.index');
        });

        // ==========================================
        // 3. WALLET
        // ==========================================
        Route::prefix('wallet')->name('wallet.')->group(function (): void {
            Route::post('topup', [WalletTopupController::class, 'topup'])
                ->middleware('throttle:5,1')
                ->name('topup');
        });
    });

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Shop\AdviceRequestController;
use App\Http\Controllers\Api\Shop\Forms\CollaborationRequestController;
use App\Http\Controllers\Api\Shop\Forms\ContactUsRequestController;

Route::middleware(['throttle:10,1'])
    ->group(function (): void {
        Route::post('contact-us', ContactUsRequestController::class)->name('contactus.store');
        Route::post('collaboration', CollaborationRequestController::class)->name('collaboration.store');
        Route::post('advice-requests', AdviceRequestController::class)->name('advice-requests.store');

    });

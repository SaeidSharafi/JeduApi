<?php

use App\Http\Controllers\Api\Shop\Forms\ContactUsRequestController;

Route::middleware(['throttle:10,1'])
    ->group(function (): void {
        Route::post('contact-us', ContactUsRequestController::class)->name('contactus.store');
    });

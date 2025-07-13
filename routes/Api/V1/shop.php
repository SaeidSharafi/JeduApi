<?php

declare(strict_types=1);

Route::middleware('auth:user')->name('shop.')->group(function () {
    Route::singleton('profile', App\Http\Controllers\Api\Shop\ProfileController::class)
        ->only(['show', 'update']);
});

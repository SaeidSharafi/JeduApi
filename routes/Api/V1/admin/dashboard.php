<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Dashboard\LiveController;
use App\Http\Controllers\Api\Admin\Dashboard\WalletController;

Route::prefix('dashboard')->name('dashboard.')->group(function (): void {
    Route::get('wallet', WalletController::class)->name('wallet');
    Route::get('live', LiveController::class)->name('live');
});

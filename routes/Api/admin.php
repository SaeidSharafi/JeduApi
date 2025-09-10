<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Settings\FooterController;

Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('footer', [FooterController::class, 'show'])->name('footer.show');
    Route::put('footer', [FooterController::class, 'update'])->name('footer.update');
});

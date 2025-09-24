<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Settings\AboutUsInfoController;
use App\Http\Controllers\Api\Admin\Settings\ContactInfoController;
use App\Http\Controllers\Api\Admin\Settings\FooterController;
use App\Http\Controllers\Api\Admin\Settings\HeaderController;
use App\Http\Controllers\Api\Admin\Settings\HomePageBlockController;
use App\Http\Controllers\Api\Admin\Settings\PartnerController;
use App\Http\Controllers\Api\Admin\Settings\SettingController;
use App\Http\Controllers\Api\Admin\Settings\Slider\SliderController;
use App\Http\Controllers\Api\Admin\Settings\Slider\UpdateSliderStatusController;
use App\Http\Controllers\Api\Admin\Settings\StudentStoryController;

Route::prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/', [SettingController::class, 'index'])
        ->name('index');
    Route::get('contact-info', [ContactInfoController::class, 'show'])
        ->name('contact-info.show');
    Route::put('contact-info', [ContactInfoController::class, 'update'])
        ->name('contact-info.update');
    Route::get('about-us', [AboutUsInfoController::class, 'show'])
        ->name('about-us.show');
    Route::put('about-us', [AboutUsInfoController::class, 'update'])
        ->name('about-us.update');
    Route::get('footer', [FooterController::class, 'show'])
        ->name('footer.show');
    Route::put('footer', [FooterController::class, 'update'])
        ->name('footer.update');
    Route::get('header', [HeaderController::class, 'show'])
        ->name('header.show');
    Route::put('header', [HeaderController::class, 'update'])
        ->name('header.update');
    Route::apiResource('slider', SliderController::class);
    Route::patch('slider/{slider}/status', UpdateSliderStatusController::class)
        ->name('slider.status');
    Route::apiResource('partner', PartnerController::class);
    Route::apiResource('home-page-block', HomePageBlockController::class);

    Route::apiResource('student-stories', StudentStoryController::class);
});

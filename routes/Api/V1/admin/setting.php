<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Content\AboutUsInfoController;
use App\Http\Controllers\Api\Admin\Content\CollaborationInfoController;
use App\Http\Controllers\Api\Admin\Content\ContactInfoController;
use App\Http\Controllers\Api\Admin\Content\FooterController;
use App\Http\Controllers\Api\Admin\Content\HeaderController;
use App\Http\Controllers\Api\Admin\Content\HomePageBlockController;
use App\Http\Controllers\Api\Admin\Content\PartnerController;
use App\Http\Controllers\Api\Admin\Content\Slider\SliderController;
use App\Http\Controllers\Api\Admin\Content\Slider\UpdateSliderStatusController;
use App\Http\Controllers\Api\Admin\Content\StudentStoryController;
use App\Http\Controllers\Api\Admin\Settings\SettingController;

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
    Route::get('collaboration', [CollaborationInfoController::class, 'show'])
        ->name('collaboration.show');
    Route::put('collaboration', [CollaborationInfoController::class, 'update'])
        ->name('collaboration.update');

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

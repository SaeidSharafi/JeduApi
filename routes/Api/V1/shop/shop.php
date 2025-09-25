<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Shop\CMS\AboutUsController;
use App\Http\Controllers\Api\Shop\CMS\ContactPageController;
use App\Http\Controllers\Api\Shop\HomePage\PartnerController;
use App\Http\Controllers\Api\Shop\HomePage\SliderController;
use App\Http\Controllers\Api\Shop\HomePage\StudentStoryController;
use App\Http\Controllers\Api\Shop\HomePageContentController;
use App\Http\Controllers\Api\Shop\Settings\FooterController;
use App\Http\Controllers\Api\Shop\Settings\HeaderController;

require __DIR__."/rate-limited.php";

// Home Page Blocks
Route::get('home-page-blocks', [HomePageContentController::class, 'index'])->name('home-page-blocks.index');
Route::get('home-page-blocks/{homePageBlock}', [HomePageContentController::class, 'show'])->name('home-page-blocks.show');

Route::get('sliders', SliderController::class)->name('sliders.index');
Route::get('header', HeaderController::class)->name('header.index');
Route::get('footer', FooterController::class)->name('footer.index');
Route::get('aboutus', AboutUsController::class)->name('aboutus.show');
Route::get('contact-page', ContactPageController::class)->name('contactpage.show');
Route::get('partners', PartnerController::class)->name('partners.index');
Route::get('student-stories', StudentStoryController::class)->name('student-stories.index');

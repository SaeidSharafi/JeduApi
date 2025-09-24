<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Shop\HomePage\HeaderController;
use App\Http\Controllers\Api\Shop\HomePage\PartnerController;
use App\Http\Controllers\Api\Shop\HomePage\SliderController;
use App\Http\Controllers\Api\Shop\HomePageContentController;
use App\Http\Controllers\Api\Shop\Shared\FooterController;

// Home Page Blocks
Route::get('home-page-blocks', [HomePageContentController::class, 'index'])->name('home-page-blocks.index');
Route::get('home-page-blocks/{homePageBlock}', [HomePageContentController::class, 'show'])->name('home-page-blocks.show');

Route::get('sliders', SliderController::class)->name('sliders.index');
Route::get('header', HeaderController::class)->name('header.index');
Route::get('footer', FooterController::class)->name('footer.index');
Route::get('partners', PartnerController::class)->name('partners.index');

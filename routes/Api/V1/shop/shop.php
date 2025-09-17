<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Shop\HomePageContentController;

// Home Page Content
Route::get('home-page-content', HomePageContentController::class)->name('home-page-content');

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Shop\CMS\AboutUsController;
use App\Http\Controllers\Api\Shop\CMS\CollaborationPageController;
use App\Http\Controllers\Api\Shop\CMS\ContactPageController;
use App\Http\Controllers\Api\Shop\HomePage\HomePageContentController;
use App\Http\Controllers\Api\Shop\HomePage\PartnerController;
use App\Http\Controllers\Api\Shop\HomePage\SliderController;
use App\Http\Controllers\Api\Shop\HomePage\StudentStoryController;
use App\Http\Controllers\Api\Shop\Product\CategoryController;
use App\Http\Controllers\Api\Shop\Product\CategoryCourseController;
use App\Http\Controllers\Api\Shop\Product\CategoryDigitalAssetController;
use App\Http\Controllers\Api\Shop\Product\CategorySeminarController;
use App\Http\Controllers\Api\Shop\Product\CourseController;
use App\Http\Controllers\Api\Shop\Product\DigitalAssetController;
use App\Http\Controllers\Api\Shop\Product\GoodForStartCoursesController;
use App\Http\Controllers\Api\Shop\Product\RelatedProductController;
use App\Http\Controllers\Api\Shop\Product\SeminarController;
use App\Http\Controllers\Api\Shop\ProductTeacherController;
use App\Http\Controllers\Api\Shop\SearchController;
use App\Http\Controllers\Api\Shop\Settings\FooterController;
use App\Http\Controllers\Api\Shop\Settings\HeaderController;
use App\Http\Controllers\Api\Shop\SuggestSearchController;
use App\Http\Controllers\Api\Shop\TeacherController;

require __DIR__.'/rate-limited.php';

// Home Page Blocks
Route::get('home-page-blocks', [HomePageContentController::class, 'index'])->name('home-page-blocks.index');
Route::get('home-page-blocks/{homePageBlock}', [HomePageContentController::class, 'show'])->name('home-page-blocks.show');

Route::get('sliders', SliderController::class)->name('sliders.index');
Route::get('header', HeaderController::class)->name('header.index');
Route::get('footer', FooterController::class)->name('footer.index');
Route::get('aboutus', AboutUsController::class)->name('aboutus.show');
Route::get('contact-page', ContactPageController::class)->name('contactpage.show');
Route::get('collaboration', CollaborationPageController::class)->name('collaboration.show');
Route::get('partners', PartnerController::class)->name('partners.index');
Route::get('student-stories', StudentStoryController::class)->name('student-stories.index');

Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('category/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('category/{category:slug}/courses', CategoryCourseController::class)->name('categories.courses');
Route::get('category/{category:slug}/seminars', CategorySeminarController::class)->name('categories.seminars');
Route::get('category/{category:slug}/digital-assets', CategoryDigitalAssetController::class)->name('categories.digital-assets');
Route::get('good-for-start/category/{category:slug}/courses', GoodForStartCoursesController::class)
    ->name('categories.courses.good-for-start');
Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('course/{product:slug}', [CourseController::class, 'show'])
    ->name('courses.show');
Route::get('seminars', [SeminarController::class, 'index'])->name('seminars.index');
Route::get('seminar/{product:slug}', [SeminarController::class, 'show'])
    ->name('seminars.show');
Route::get('digital-assets', [DigitalAssetController::class, 'index'])->name('digital-assets.index');
Route::get('digital-asset/{product:slug}', [DigitalAssetController::class, 'show'])
    ->name('digital-assets.show');

Route::get('search', SearchController::class)->name('search');
Route::get('search/suggest', SuggestSearchController::class)->name('search.suggest');

Route::get('teachers/{teacher:uuid}', [TeacherController::class, 'show'])->name('teachers.show');
//Route::get('teachers', [TeacherController::class, 'index'])->name('teachers.index');
Route::get('product/{product:slug}/teachers', ProductTeacherController::class)->name('product.teachers');

Route::get('product/{product:slug}/related/{relation_type}', RelatedProductController::class)
    ->name('product.related');

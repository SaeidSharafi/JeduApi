<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Shop\Settings\SiteIdentityController;
use App\Http\Controllers\Testing\TestingDatabaseResetController;

Illuminate\Support\Facades\Route::webhooks('webhooks/github-deployer', 'github-deployer');

// The admin panel's shell (tab title + favicon) must render before a staff
// session exists, so this public read lives outside the authenticated admin
// group rather than on the storefront's origin.
Route::get('admin/site-identity', SiteIdentityController::class)->name('admin.site-identity.show');
require __DIR__.'/auth.php';
require __DIR__.'/customer.php';

if (app()->environment('e2e')) {
    Route::post('/e2e/reset', [TestingDatabaseResetController::class, 'reset']);
}

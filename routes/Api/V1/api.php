<?php

declare(strict_types=1);

use App\Http\Controllers\Testing\TestingDatabaseResetController;

Illuminate\Support\Facades\Route::webhooks('webhooks/github-deployer', 'github-deployer');
require __DIR__.'/auth.php';
require __DIR__.'/customer.php';

if (app()->environment('e2e')) {
    Route::post('/e2e/reset', [TestingDatabaseResetController::class, 'reset']);
}

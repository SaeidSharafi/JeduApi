<?php

declare(strict_types=1);

Illuminate\Support\Facades\Route::webhooks('webhooks/github-deployer', 'github-deployer');
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/select_option.php';

require __DIR__.'/customer.php';

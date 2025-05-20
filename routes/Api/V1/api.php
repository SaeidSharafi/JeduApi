<?php

declare(strict_types=1);

Route::webhooks('webhooks/github-deployer', 'github-deployer');
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/course.php';

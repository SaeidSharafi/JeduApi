<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/mock-admin/{path?}', function (): mixed {
    abort_unless(in_array(config('app.env'), ['local', 'testing'], true), 404);

    return view('mock-admin.index');
})->where('path', '.*')->name('mock-admin');

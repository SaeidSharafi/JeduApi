<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health-check', function () {
    return response()
        ->json([
            'status' => 'ok',
            'message' => 'API is up and running',
        ]);
});

require_once 'auth.php';

require_once 'auth.php';

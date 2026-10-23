<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'PolicyFlow API',
        'status' => 'ok',
    ]);
});

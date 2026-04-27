<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'FinBridge API is running 🚀'
    ]);
});

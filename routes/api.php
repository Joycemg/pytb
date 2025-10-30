
<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    $payload = ['ok' => true, 'at' => now()->toIso8601String()];
    return response()->json($payload);
})->middleware('throttle:60,1');

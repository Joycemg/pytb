
<?php

use App\Http\Controllers\BlogFeedController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    $payload = ['ok' => true, 'at' => now()->toIso8601String()];
    return response()->json($payload);
})->middleware('throttle:60,1');

Route::get('/blog/posts', [BlogFeedController::class, 'json'])
    ->name('api.blog.posts')
    ->middleware('throttle:60,1');

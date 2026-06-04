<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicMediaController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {
    return response()->json(['message' => 'This is a test route']);
});

Route::get(config('media.route_prefix', 'media') . '/{publicKey}/thumb/{filename?}', [PublicMediaController::class, 'thumbnail'])
    ->where('publicKey', '[A-Za-z0-9]+')
    ->where('filename', '.*')
    ->name('media.public.thumbnail');

Route::get(config('media.route_prefix', 'media') . '/{publicKey}/{filename?}', [PublicMediaController::class, 'show'])
    ->where('publicKey', '[A-Za-z0-9]+')
    ->where('filename', '.*')
    ->name('media.public.show');

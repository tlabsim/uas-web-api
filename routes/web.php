<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\PublicPostAttachmentController;

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

Route::get(config('media.attachment_route_prefix', 'attachments') . '/{publicKey}/{filename?}', [PublicPostAttachmentController::class, 'show'])
    ->where('publicKey', '[A-Za-z0-9]+')
    ->where('filename', '.*')
    ->name('post-attachments.public.show');

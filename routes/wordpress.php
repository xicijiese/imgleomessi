<?php

use App\Http\Controllers\WordPressAlbumController;
use App\Http\Middleware\AuthorizeWordPressAlbum;
use Illuminate\Support\Facades\Route;

Route::prefix('wordpress/v1')->middleware([AuthorizeWordPressAlbum::class, 'throttle:120,1'])->group(function (): void {
    Route::get('/health', fn () => response()->json(['version' => 1, 'name' => '梅西影像档案库']));
    Route::get('/resolve', [WordPressAlbumController::class, 'resolve']);
    Route::get('/albums/{album}', [WordPressAlbumController::class, 'show'])->whereNumber('album');
});

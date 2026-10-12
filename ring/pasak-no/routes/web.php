<?php

use App\Http\Controllers\DemoUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $hits = collect(@file(storage_path('logs/waf-hits.log'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
        ->map(fn ($line) => json_decode($line, true))
        ->filter()
        ->reverse()
        ->take(20)
        ->values();
    $pasakInstalled = class_exists(\Internal\Pasak\PasakServiceProvider::class)
        || file_exists(config_path('pasak.php'));

    return view('welcome', ['wafHits' => $hits, 'wafCount' => count($hits), 'pasakInstalled' => $pasakInstalled]);
});

Route::get('/waf-hits.json', function () {
    $hits = collect(@file(storage_path('logs/waf-hits.log'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
        ->map(fn ($line) => json_decode($line, true))
        ->filter()
        ->reverse()
        ->take(50)
        ->values();

    return response()->json(['count' => $hits->count(), 'hits' => $hits]);
});

Route::post('/waf-hits/clear', function () {
    @file_put_contents(storage_path('logs/waf-hits.log'), '');

    return back();
})->name('waf-hits.clear');

// DEMO AUDIT SENGAJA RENTAN — buktikan upload shell.php.jpg benar-benar masuk.
Route::get('/upload', [DemoUploadController::class, 'form']);
Route::post('/upload', [DemoUploadController::class, 'store']);
Route::get('/demo-upload', fn () => redirect('/upload'));

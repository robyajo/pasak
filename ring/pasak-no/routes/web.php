<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoShellController;
use App\Http\Controllers\DemoUploadController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\TagController;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Support\Facades\Route;
use Internal\Pasak\PasakServiceProvider;

Route::get('/', function () {
    $hits = collect(@file(storage_path('logs/waf-hits.log'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
        ->map(fn ($line) => json_decode($line, true))
        ->filter()
        ->reverse()
        ->take(20)
        ->values();
    $pasakInstalled = class_exists(PasakServiceProvider::class)
        || file_exists(config_path('pasak.php'));

    $featured = Post::with(['category', 'tags'])->where('is_published', true)->latest()->first();
    $latestPosts = Post::with(['category', 'tags'])->where('is_published', true)->latest()->skip(1)->take(6)->get();
    $categories = Category::withCount('posts')->orderBy('name')->get();

    return view('welcome', [
        'wafHits' => $hits,
        'wafCount' => count($hits),
        'pasakInstalled' => $pasakInstalled,
        'featured' => $featured,
        'latestPosts' => $latestPosts,
        'categories' => $categories,
    ]);
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

// DEMO EKSPLOITASI — eksekusi berkas unggahan (?cmd=...). Hanya pasak-no.
Route::get('/shell/{name}', [DemoShellController::class, 'exec'])
    ->where('name', '[A-Za-z0-9._%-]+');

Route::resource('contacts', ContactController::class);

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth')->name('dashboard');
Route::resource('posts', PostController::class);
Route::resource('categories', CategoryController::class)->except(['show']);
Route::resource('tags', TagController::class)->except(['show']);

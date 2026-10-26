<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KeamananController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\TagController;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $featured = Post::with(['category', 'tags'])->where('is_published', true)->latest()->first();
    $latestPosts = Post::with(['category', 'tags'])->where('is_published', true)->latest()->skip(1)->take(6)->get();
    $categories = Category::withCount('posts')->orderBy('name')->get();

    return view('welcome', compact('featured', 'latestPosts', 'categories'));
});

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

Route::middleware('auth')->prefix('keamanan')->name('keamanan.')->group(function () {
    // 1. Overview
    Route::get('/', [KeamananController::class, 'index'])->name('index');

    // 2. Logs
    Route::get('logs', [KeamananController::class, 'logs'])->name('logs');
    Route::delete('logs', [KeamananController::class, 'clearLogs'])->name('logs.clear');
    Route::delete('logs/{id}', [KeamananController::class, 'destroyLog'])->name('logs.destroy');

    // 3. Blocked IPs
    Route::get('blocked-ips', [KeamananController::class, 'blockedIps'])->name('blocked-ips');
    Route::post('block', [KeamananController::class, 'blockIp'])->name('block');
    Route::post('unblock/{ip}', [KeamananController::class, 'unblockIp'])->name('unblock');
    Route::patch('blocked-ips/{id}/toggle', [KeamananController::class, 'toggleBlockedIp'])->name('blocked-ips.toggle');
    Route::delete('blocked-ips/{id}', [KeamananController::class, 'destroyBlockedIp'])->name('blocked-ips.destroy');

    // 4. Allowed IPs (Whitelist)
    Route::get('allowed-ips', [KeamananController::class, 'allowedIps'])->name('allowed-ips');
    Route::post('allowed-ips', [KeamananController::class, 'storeAllowedIp'])->name('allowed-ips.store');
    Route::patch('allowed-ips/{id}/toggle', [KeamananController::class, 'toggleAllowedIp'])->name('allowed-ips.toggle');
    Route::delete('allowed-ips/{id}', [KeamananController::class, 'destroyAllowedIp'])->name('allowed-ips.destroy');

    // 5. Server Audit
    Route::get('server', [KeamananController::class, 'server'])->name('server');
    Route::post('server/baseline', [KeamananController::class, 'serverBaseline'])->name('server.baseline');
    Route::delete('server/baseline', [KeamananController::class, 'serverBaselineDestroy'])->name('server.baseline.destroy');
    Route::delete('server/suspicious-files', [KeamananController::class, 'deleteSuspiciousFile'])->name('server.suspicious-files.destroy');
    Route::delete('lockouts/{id}', [KeamananController::class, 'releaseLockout'])->name('lockouts.destroy');

    // 6. User Sessions & Trusted IPs
    Route::get('sessions', [KeamananController::class, 'sessions'])->name('sessions');
    Route::delete('sessions/{id}', [KeamananController::class, 'destroySession'])->name('sessions.destroy');
    Route::post('trusted-ips/my-ip', [KeamananController::class, 'storeTrustedIp'])->name('trusted-ips.store');
    Route::delete('trusted-ips/{id}', [KeamananController::class, 'destroyTrustedIp'])->name('trusted-ips.destroy');

    // 7. Tickets
    Route::get('tickets', [KeamananController::class, 'tickets'])->name('tickets');
    Route::post('tickets/{id}/respond', [KeamananController::class, 'respondTicket'])->name('tickets.respond');
    Route::delete('tickets/{id}', [KeamananController::class, 'destroyTicket'])->name('tickets.destroy');

    // 8. Settings
    Route::get('settings', [KeamananController::class, 'settings'])->name('settings');
    Route::post('settings', [KeamananController::class, 'updateSettings'])->name('settings.update');
});

require __DIR__.'/pasak.php';

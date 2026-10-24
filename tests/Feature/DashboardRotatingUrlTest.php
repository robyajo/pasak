<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Internal\Pasak\Http\Controllers\Dashboard\PasakDashboardEntryController;
use Internal\Pasak\Http\Middleware\PasakRotatingDashboard;
use Internal\Pasak\Services\PasakDashboardUrlService;

beforeEach(function () {
    config()->set('pasak.dashboard.enabled', true);
    config()->set('pasak.dashboard.prefix', 'pasak');
    config()->set('pasak.dashboard.rotating', true);
    config()->set('pasak.dashboard.single_use', true);
    config()->set('pasak.dashboard.grace_seconds', 120);
    config()->set('pasak.dashboard.token_length', 16);

    $admin = $this->createAdminUser();
    $this->actingAs($admin);

    Route::middleware(['web', 'auth'])
        ->get('pasak', PasakDashboardEntryController::class)
        ->name('pasak.dashboard.entry');

    Route::middleware(['web', 'auth', PasakRotatingDashboard::class])
        ->prefix('{pasakSlug}')
        ->where(['pasakSlug' => 'pasak-[A-Za-z0-9]{12,32}'])
        ->name('pasak.dashboard.')
        ->group(function () {
            Route::get(
                '/',
                fn () => response('overview '.route('pasak.dashboard.logs')),
            )->name('overview');
            Route::get(
                'logs',
                fn () => response('logs '.route('pasak.dashboard.logs')),
            )->name('logs');
            Route::get(
                'threat-map',
                fn () => response(
                    'map '.route('pasak.dashboard.threat-map.feed'),
                ),
            )->name('threat-map');
            Route::get(
                'threat-map/feed',
                fn () => response()->json(['ok' => true]),
            )->name('threat-map.feed');
        });
});

test('service membuat slug acak berformat pasak-token', function () {
    $service = app(PasakDashboardUrlService::class);
    $request = Request::create('/pasak', 'GET');

    $slug = $service->currentSlug($request);

    expect($slug)
        ->toStartWith('pasak-')
        ->and($service->matchesFormat($slug))
        ->toBeTrue()
        ->and(strlen($slug) - strlen('pasak-'))
        ->toBe(16);
});

test('pintu stabil mengalihkan ke slug acak milik sesi', function () {
    $response = $this->get('/pasak');

    $response->assertRedirect();
    $target = $response->headers->get('Location');

    expect($target)->toMatch('/pasak-[A-Za-z0-9]{12,32}/');
});

test(
    'slug valid tersaji dan link memakai token baru sekali pakai',
    function () {
        $entry = $this->get('/pasak');
        $firstUrl = $entry->headers->get('Location');
        $firstPath = parse_url($firstUrl, PHP_URL_PATH);

        $page = $this->get($firstPath.'/logs');
        $page->assertOk();

        $body = $page->getContent();
        preg_match('/pasak-[A-Za-z0-9]{12,32}/', (string) $body, $m);

        expect($m[0] ?? '')
            ->not->toBe('')
            ->and($m[0] ?? '')
            ->not->toBe(trim($firstPath, '/'));
    },
);

test('reload url lama dialihkan ke url baru bukan 404', function () {
    $entry = $this->get('/pasak');
    $firstPath = parse_url(
        (string) $entry->headers->get('Location'),
        PHP_URL_PATH,
    );

    $first = $this->get($firstPath);
    $first->assertOk();

    // Reload URL yang sama (sudah diputar): harus 302 ke slug baru.
    $reload = $this->get($firstPath);
    $reload->assertRedirect();

    $newPath = parse_url(
        (string) $reload->headers->get('Location'),
        PHP_URL_PATH,
    );

    expect($newPath)
        ->not->toBe($firstPath)
        ->and((string) $newPath)
        ->toMatch('/pasak-[A-Za-z0-9]{12,32}/');

    // Mengikuti redirect hasilnya 200.
    $this->get($newPath)->assertOk();
});

test('slug asing ditolak 404 agar dashboard tersamar', function () {
    $this->get('/pasak-zzzzzzzzzzzzzzzz/logs')->assertNotFound();
    $this->get('/pasak/logs')->assertNotFound();
});

test('feed polling tidak memutar token sehingga tidak balapan', function () {
    $entry = $this->get('/pasak');
    $firstPath = parse_url(
        (string) $entry->headers->get('Location'),
        PHP_URL_PATH,
    );

    $this->get($firstPath.'/threat-map')->assertOk();

    // Ambil slug aktif dari sesi untuk feed.
    $service = app(PasakDashboardUrlService::class);
    $current = session(PasakDashboardUrlService::SESSION_KEY);

    expect($current)->toBeString();

    $this->get('/'.$current.'/threat-map/feed')->assertOk();
    $this->get('/'.$current.'/threat-map/feed')->assertOk();
});

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Internal\Pasak\Services\PasakDashboardUrlService;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('pasak.dashboard.enabled', true);
    config()->set('pasak.dashboard.prefix', 'pasak');
    config()->set('pasak.dashboard.rotating', true);
    config()->set('pasak.dashboard.single_use', true);
    config()->set('pasak.dashboard.grace_seconds', 120);
    config()->set('pasak.dashboard.token_length', 16);
    config()->set(
        'pasak.dashboard.secret',
        'WT3J5pZLlQJkVPueACpFbxzBH0g1mSoB0Gcn7yKhKVp9WTvi',
    );
});

test(
    'pintu masuk stabil /pasak mengalihkan (302) ke URL acak milik sesi',
    function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/pasak');

        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        expect($location)->toMatch('#/pasak-[A-Za-z0-9]{12,32}/?$#');
    },
);

test(
    'alias stabil /pasak/logs dan subpage lainnya dialihkan (302) ke slug sesi',
    function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/pasak/logs');

        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        expect($location)->toMatch('#/pasak-[A-Za-z0-9]{12,32}/logs#');
    },
);

test(
    'slug yang valid menyajikan halaman HTML dashboard dengan status 200',
    function () {
        $user = User::factory()->create();

        $entry = $this->actingAs($user)->get('/pasak');
        $targetUrl = $entry->headers->get('Location');
        $path = parse_url($targetUrl, PHP_URL_PATH);

        $page = $this->actingAs($user)->get($path);
        $page->assertStatus(200);
    },
);

test(
    'token diputar setiap akses halaman HTML sehingga URL baru dihasilkan',
    function () {
        $user = User::factory()->create();

        $entry = $this->actingAs($user)->get('/pasak');
        $firstPath = parse_url($entry->headers->get('Location'), PHP_URL_PATH);

        // Akses pertama dengan slug pertama
        $page1 = $this->actingAs($user)->get($firstPath);
        $page1->assertStatus(200);

        // Ambil slug terbaru dari sesi setelah rotasi
        $newSlug = session(PasakDashboardUrlService::SESSION_KEY);

        expect($newSlug)
            ->not->toBeNull()
            ->and('/'.$newSlug)
            ->not->toBe($firstPath);
    },
);

test(
    'url lama di dalam masa tenggang grace period dialihkan ke url baru bukan 404',
    function () {
        $user = User::factory()->create();

        $entry = $this->actingAs($user)->get('/pasak');
        $firstPath = parse_url(
            (string) $entry->headers->get('Location'),
            PHP_URL_PATH,
        );

        // Kunjungi halaman sekali agar token berotasi
        $this->actingAs($user)->get($firstPath)->assertStatus(200);

        // Buka kembali URL lama yang baru saja diputar (simulasi reload browser atau back button)
        $reloadResponse = $this->actingAs($user)->get($firstPath);
        $reloadResponse->assertStatus(302);

        $redirectLocation = $reloadResponse->headers->get('Location');
        expect($redirectLocation)
            ->toMatch('#/pasak-[A-Za-z0-9]{12,32}#')
            ->and(parse_url((string) $redirectLocation, PHP_URL_PATH))
            ->not->toBe($firstPath);
    },
);

test(
    'reload setelah masa tenggang lewat dialihkan ke slug aktif, bukan 404',
    function () {
        $user = User::factory()->create();

        $entry = $this->actingAs($user)->get('/pasak');
        $firstPath = parse_url(
            (string) $entry->headers->get('Location'),
            PHP_URL_PATH,
        );

        $this->actingAs($user)->get($firstPath)->assertStatus(200);

        // Simulasikan halaman dibiarkan terbuka lebih dari grace_seconds
        // (mis. 30 menit) lalu di-reload.
        session([PasakDashboardUrlService::PREV_AT_KEY => time() - 1800]);

        $reload = $this->actingAs($user)->get($firstPath);
        $reload->assertStatus(302);

        $location = (string) $reload->headers->get('Location');

        expect($location)->toMatch('#/pasak-[A-Za-z0-9]{12,32}#');

        $this->actingAs($user)
            ->get(parse_url($location, PHP_URL_PATH))
            ->assertStatus(200);
    },
);

test(
    'slug asing pada navigasi html dialihkan ke slug aktif sesi',
    function () {
        $user = User::factory()->create();

        // Navigasi GET dengan token yang bukan milik sesi (mis. setelah
        // login ulang / sesi baru) harus pulih, bukan berakhir 404.
        $response = $this->actingAs($user)->get('/pasak-fake123456789012/logs');

        $response->assertStatus(302);

        $location = (string) $response->headers->get('Location');

        expect($location)->toMatch('#/pasak-[A-Za-z0-9]{12,32}/logs#');

        $this->actingAs($user)
            ->get(parse_url($location, PHP_URL_PATH))
            ->assertStatus(200);
    },
);

test(
    'slug asing pada feed tetap 404 agar dashboard tetap tersamar',
    function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/pasak-fake123456789012/threat-map/feed')
            ->assertStatus(404);
    },
);

test(
    'endpoint polling threat-map/feed tidak memutar token agar tidak balapan',
    function () {
        $user = User::factory()->create();

        $entry = $this->actingAs($user)->get('/pasak');
        $firstPath = parse_url(
            (string) $entry->headers->get('Location'),
            PHP_URL_PATH,
        );

        $this->actingAs($user)
            ->get($firstPath.'/threat-map')
            ->assertStatus(200);

        // Ambil slug aktif dari sesi untuk feed
        $slug = session(PasakDashboardUrlService::SESSION_KEY);
        expect($slug)->toBeString();

        $feed1 = $this->actingAs($user)->getJson(
            '/'.$slug.'/threat-map/feed',
        );
        $feed1->assertStatus(200);
        expect(session(PasakDashboardUrlService::SESSION_KEY))->toBe($slug);

        $feed2 = $this->actingAs($user)->getJson(
            '/'.$slug.'/threat-map/feed',
        );
        $feed2->assertStatus(200);
        expect(session(PasakDashboardUrlService::SESSION_KEY))->toBe($slug);
    },
);

test('pengguna yang belum login dialihkan ke halaman login', function () {
    $response = $this->get('/pasak');

    $response->assertRedirect('/login');
});

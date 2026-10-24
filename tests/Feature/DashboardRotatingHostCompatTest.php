<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

function publishPasakViewsForCompat(): string
{
    $target = resource_path('views/pages/pasak');

    if (! File::isDirectory($target)) {
        File::copyDirectory(
            dirname(__DIR__, 2).'/stubs/blade/pages/pasak',
            $target,
        );
    }

    return $target;
}

function loadRealRotatingRoutes(): void
{
    config()->set('pasak.dashboard.enabled', true);
    config()->set('pasak.dashboard.rotating', true);
    config()->set('pasak.dashboard.prefix', 'pasak');
    config()->set('pasak.dashboard.single_use', true);
    config()->set('pasak.dashboard.grace_seconds', 120);
    config()->set('pasak.dashboard.token_length', 16);

    // Simulasi fallback boot PasakServiceProvider::registerDashboardUrlDefaults().
    URL::defaults(['pasakSlug' => 'pasak']);

    if (! Route::has('pasak.dashboard.entry')) {
        require dirname(__DIR__, 2).'/routes/pasak-dashboard-blade.php';

        // Testbench tidak memanggil RouteServiceProvider (yang me-refresh
        // name lookup), jadi segarkan manual seperti aplikasi real.
        Route::getRoutes()->refreshNameLookups();
    }
}

beforeEach(function () {
    publishPasakViewsForCompat();
    loadRealRotatingRoutes();

    $this->actingAs($this->createAdminUser());
});

afterEach(function () {
    File::deleteDirectory(resource_path('views/pages/pasak'));
});

test(
    'host view calling dashboard route helper never fatals in rotating mode',
    function () {
        Route::middleware(['web'])->get(
            '/__pasak_host',
            fn () => 'link: '.route('pasak.dashboard.logs'),
        );

        $response = $this->get('/__pasak_host');

        $response->assertOk();

        // Fallback stabil — alias redirect yang akan mengantar ke token aktif.
        expect($response->getContent())->toContain('/pasak/logs');
    },
);

test('stable alias redirects to the active rotating slug', function () {
    $response = $this->get('/pasak/logs');

    $response->assertRedirect();

    $target = (string) $response->headers->get('Location');

    expect($target)->toMatch('/pasak-[A-Za-z0-9]{12,32}\\/logs/');

    // Ikuti redirect: halaman log tersaji dengan link token baru.
    $page = $this->get(parse_url($target, PHP_URL_PATH));
    $page->assertOk();
});

test(
    'entry plus alias keep old bookmarks working across rotation',
    function () {
        $entry = $this->get('/pasak');
        $entry->assertRedirect();

        $first = (string) $entry->headers->get('Location');
        $firstPath = parse_url($first, PHP_URL_PATH);

        // Kunjungi via slug, lalu buka lagi bookmark stabil lama.
        $this->get($firstPath)->assertOk();

        $alias = $this->get('/pasak/threat-map');
        $alias->assertRedirect();

        $aliasTarget = (string) $alias->headers->get('Location');

        expect($aliasTarget)->toMatch(
            '/pasak-[A-Za-z0-9]{12,32}\\/threat-map/',
        );

        $this->get(parse_url($aliasTarget, PHP_URL_PATH))->assertOk();
    },
);

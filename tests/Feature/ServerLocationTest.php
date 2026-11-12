<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Internal\Pasak\Http\Controllers\Dashboard\PasakThreatMapController;
use Internal\Pasak\Models\PasakSetting;
use Internal\Pasak\PasakServiceProvider;
use Internal\Pasak\Services\PasakServerSecurityService;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Support\PasakDashboardView;
use Internal\Pasak\Tests\TestUser;

beforeEach(function () {
    Cache::flush();
    PasakSetting::query()
        ->whereIn('key', [
            'server_location_name',
            'server_latitude',
            'server_longitude',
            'server_timezone',
        ])
        ->delete();

    config()->set('pasak.dashboard.enabled', true);
    config()->set('pasak.dashboard.driver', 'blade');

    if (! Route::has('pasak.dashboard.server.location')) {
        require __DIR__.'/../../routes/pasak-dashboard-blade.php';
        Route::getRoutes()->refreshNameLookups();
    }
});

afterEach(function () {
    PasakSetting::query()
        ->whereIn('key', [
            'server_location_name',
            'server_latitude',
            'server_longitude',
            'server_timezone',
        ])
        ->delete();
    Cache::flush();
});

test(
    'admin can retrieve and save server physical location and timezone via dashboard',
    function () {
        $admin = TestUser::create([
            'name' => 'Admin Location',
            'email' => 'admin-location@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $postData = [
            'server_location_name' => 'Data Center Kominfo, Jakarta (ID)',
            'server_latitude' => -6.2088,
            'server_longitude' => 106.8456,
            'server_timezone' => 'Asia/Jakarta',
        ];

        $response = $this->actingAs($admin)->post(
            route('pasak.dashboard.server.location'),
            $postData,
        );

        $response->assertRedirect();
        $response->assertSessionHas(
            'toast',
            fn ($toast) => $toast['type'] === 'success',
        );

        // Pastikan tersimpan di pasak_settings
        $location = PasakSetting::getServerLocation();
        expect($location['name'])
            ->toBe('Data Center Kominfo, Jakarta (ID)')
            ->and($location['latitude'])
            ->toBe(-6.2088)
            ->and($location['longitude'])
            ->toBe(106.8456)
            ->and($location['timezone'])
            ->toBe('Asia/Jakarta')
            ->and($location['effective_timezone'])
            ->toBe('Asia/Jakarta')
            ->and($location['is_custom'])
            ->toBeTrue();

        // Verifikasi PasakDashboardView::timezone() menghasilkan Asia/Jakarta
        expect(PasakDashboardView::timezone())->toBe('Asia/Jakarta');
    },
);

test(
    'threat map target automatically uses coordinates configured from server location',
    function () {
        Http::fake();

        PasakSetting::setServerLocation(
            'Pusat Data Bandung (ID)',
            -6.9175,
            107.6191,
            'Asia/Jakarta',
        );

        $this->app['view']->share('errors', new ViewErrorBag);

        $response = app(PasakThreatMapController::class)->index(
            Request::create('/__pasak_test_threat_loc'),
            app(PasakService::class),
        );

        $payload = $response->getData()['payload'];

        expect($payload['target']['name'])
            ->toBe('Pusat Data Bandung (ID)')
            ->and($payload['target']['lat'])
            ->toBe(-6.9175)
            ->and($payload['target']['lon'])
            ->toBe(107.6191);
    },
);

test(
    'clearing location resets threat map target to default or auto detect',
    function () {
        Http::fake();

        PasakSetting::setServerLocation(null, null, null, null);

        $this->app['view']->share('errors', new ViewErrorBag);

        $response = app(PasakThreatMapController::class)->index(
            Request::create('/__pasak_test_threat_loc_clear'),
            app(PasakService::class),
        );

        $payload = $response->getData()['payload'];

        expect($payload['target']['lat'])
            ->toBe(0.5071)
            ->and($payload['target']['lon'])
            ->toBe(101.4478);
    },
);

test(
    'location input validates latitude and longitude ranges and timezone validity',
    function () {
        $admin = TestUser::create([
            'name' => 'Admin Validation',
            'email' => 'admin-val@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Latitude out of range (> 90)
        $response = $this->actingAs($admin)->post(
            route('pasak.dashboard.server.location'),
            [
                'server_location_name' => 'Invalid North Pole',
                'server_latitude' => 195.5,
                'server_longitude' => 50.0,
                'server_timezone' => 'Asia/Jakarta',
            ],
        );

        $response->assertSessionHasErrors(['server_latitude']);

        // Longitude out of range (> 180)
        $response2 = $this->actingAs($admin)->post(
            route('pasak.dashboard.server.location'),
            [
                'server_location_name' => 'Invalid Meridian',
                'server_latitude' => 45.0,
                'server_longitude' => 250.0,
                'server_timezone' => 'Asia/Jakarta',
            ],
        );

        $response2->assertSessionHasErrors(['server_longitude']);

        // Invalid timezone identifier
        $response3 = $this->actingAs($admin)->post(
            route('pasak.dashboard.server.location'),
            [
                'server_location_name' => 'Mars Colony',
                'server_latitude' => -10.0,
                'server_longitude' => 120.0,
                'server_timezone' => 'Mars/Olympus_Mons',
            ],
        );

        $response3->assertSessionHasErrors(['server_timezone']);
    },
);

test('admin can manage server location and timezone via REST API', function () {
    $admin = TestUser::create([
        'name' => 'Admin API Loc',
        'email' => 'admin-apiloc@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);

    $postResponse = $this->actingAs($admin)->postJson(
        '/api/pasak/server/location',
        [
            'server_location_name' => 'Surabaya Disaster Recovery Center (ID)',
            'server_latitude' => -7.2575,
            'server_longitude' => 112.7521,
            'server_timezone' => 'Asia/Makassar',
        ],
    );

    $postResponse->assertSuccessful()->assertJson([
        'success' => true,
        'data' => [
            'name' => 'Surabaya Disaster Recovery Center (ID)',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'timezone' => 'Asia/Makassar',
            'effective_timezone' => 'Asia/Makassar',
            'is_custom' => true,
        ],
    ]);

    $getResponse = $this->actingAs($admin)->getJson(
        '/api/pasak/server/location',
    );
    $getResponse->assertSuccessful()->assertJson([
        'success' => true,
        'data' => [
            'name' => 'Surabaya Disaster Recovery Center (ID)',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'timezone' => 'Asia/Makassar',
            'effective_timezone' => 'Asia/Makassar',
        ],
    ]);
});

test(
    'server security service environmentInfo reflects the configured timezone',
    function () {
        PasakSetting::setServerTimezone('Asia/Jayapura');

        $env = app(PasakServerSecurityService::class)->environmentInfo();

        expect($env['timezone'])
            ->toBe('Asia/Jayapura')
            ->and(PasakDashboardView::timezone())
            ->toBe('Asia/Jayapura');
    },
);

test('upgrade command injects missing server location route into host routes file', function () {
    $dummyRoute = base_path('routes/pasak.php');
    $dummyApiRoute = base_path('routes/pasak-api.php');

    File::ensureDirectoryExists(base_path('routes'));
    File::put($dummyRoute, "<?php\nRoute::post('server/suspicious-files/unverify', [PasakServerController::class, 'unverifySuspiciousFile'])->name('server.suspicious-files.unverify');\n");
    File::put($dummyApiRoute, "<?php\nRoute::post('server/suspicious-files/unverify', [PasakServerSecurityApiController::class, 'unverifySuspiciousFile'])->name('server.suspicious-files.unverify');\n");

    try {
        $this->artisan('pasak:upgrade', ['--force' => true, '--no-composer' => true])
            ->assertExitCode(0);

        expect(File::get($dummyRoute))->toContain("'server.location'")
            ->and(File::get($dummyApiRoute))->toContain("'server.location'");
    } finally {
        if (File::exists($dummyRoute)) {
            File::delete($dummyRoute);
        }
        if (File::exists($dummyApiRoute)) {
            File::delete($dummyApiRoute);
        }
    }
});

test('service provider automatically registers essential fallback routes when host routes lack them', function () {
    // Pastikan fallback route dipanggil
    $provider = new PasakServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('ensureEssentialDashboardRoutes');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(Route::has('pasak.dashboard.server.location'))->toBeTrue();
});

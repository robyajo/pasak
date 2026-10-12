<?php

use Illuminate\Support\Facades\Route;
use Internal\Pasak\Http\Middleware\InjectAnnouncementBanner;

beforeEach(function () {
    config()->set('pasak.announcement.enabled', true);
    config()->set('pasak.announcement.paths', ['__pasak_home']);

    Route::middleware(InjectAnnouncementBanner::class)->group(function () {
        Route::get('/__pasak_home', fn () => response(
            '<html><head><title>Home</title></head><body><h1>Welcome</h1></body></html>'
        ));
        Route::get('/__pasak_inner', fn () => response(
            '<html><body><h1>Inner</h1></body></html>'
        ));
        Route::get('/__pasak_json', fn () => response()->json(['ok' => true]));
    });
});

test('announcement banner and dialog are injected on configured homepage', function () {
    $response = $this->get('/__pasak_home');

    $response->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('id="psk-announce-bar"')
        ->and($html)->toContain('Dilindungi')
        ->and($html)->toContain('id="psk-announce-modal"')
        ->and($html)->toContain('pasak-announce-')
        ->and($html)->toContain('<h1>Welcome</h1>');
});

test('announcement is skipped outside configured paths', function () {
    $html = $this->get('/__pasak_inner')->getContent();

    expect($html)->not->toContain('psk-announce-bar');
});

test('announcement is skipped for json responses', function () {
    $html = $this->getJson('/__pasak_json')->getContent();

    expect($html)->not->toContain('psk-announce-bar');
});

test('announcement can be disabled via config', function () {
    config()->set('pasak.announcement.enabled', false);

    $html = $this->get('/__pasak_home')->getContent();

    expect($html)->not->toContain('psk-announce-bar');
});

test('announcement middleware alias is registered', function () {
    expect(app('router')->getMiddleware())->toHaveKey('pasak.announce');
});

test('env stub documents the announcement toggle', function () {
    $stub = file_get_contents(dirname(__DIR__, 2).'/stubs/env.stub');

    expect($stub)->toContain('PASAK_ANNOUNCEMENT_ENABLED');
});

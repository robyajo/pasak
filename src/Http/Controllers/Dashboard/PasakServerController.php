<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Internal\Pasak\Models\PasakLoginAttempt;
use Internal\Pasak\Models\PasakSetting;
use Internal\Pasak\Services\PasakServerSecurityService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Server audit page for the Blade monitoring dashboard.
 */
class PasakServerController extends Controller
{
    use RespondsWithPasakDashboard;

    public function index(
        Request $request,
        PasakServerSecurityService $server,
    ): View {
        $force = $request->boolean('refresh');

        if ($force) {
            $server->forget();
        }

        return $this->dashboardResponse('pages.pasak.server', [
            'report' => $server->scan($force),
            'environment' => $server->environmentInfo(),
            'baseline' => $server->baselineSummary(),
            'integrity' => $server->integrityReport(),
            'suspicious' => $server->suspiciousFiles($force),
            'verified' => $server->verifiedFilesList(),
            'deleted' => $server->deletedFilesList(),
            'lockouts' => $server->activeLockouts(),
            'serverLocation' => PasakSetting::getServerLocation(),
            'urls' => [
                'index' => route('pasak.dashboard.server'),
                'refresh' => route('pasak.dashboard.server', [
                    'refresh' => 1,
                ]),
                'baseline' => route('pasak.dashboard.server.baseline'),
                'baselineDestroy' => route(
                    'pasak.dashboard.server.baseline.destroy',
                ),
                'suspiciousDestroy' => route(
                    'pasak.dashboard.server.suspicious-files.destroy',
                ),
                'suspiciousDownload' => route(
                    'pasak.dashboard.server.suspicious-files.download',
                ),
                'suspiciousVerify' => route(
                    'pasak.dashboard.server.suspicious-files.verify',
                ),
                'suspiciousUnverify' => Route::has(
                    'pasak.dashboard.server.suspicious-files.unverify',
                )
                    ? route('pasak.dashboard.server.suspicious-files.unverify')
                    : '#',
                'lockoutDestroy' => route('pasak.dashboard.lockouts.destroy', [
                    'id' => '__ID__',
                ]),
                'updateLocation' => Route::has('pasak.dashboard.server.location')
                    ? route('pasak.dashboard.server.location')
                    : (Route::has('pasak.dashboard.server') ? route('pasak.dashboard.server') : '#'),
                'threatMap' => Route::has('pasak.dashboard.threat-map')
                    ? route('pasak.dashboard.threat-map')
                    : '#',
            ],
        ]);
    }

    public function createBaseline(
        PasakServerSecurityService $server,
    ): RedirectResponse {
        $server->createBaseline(auth()->id());

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Integrity baseline created.'),
        ]);
    }

    public function deleteBaseline(
        PasakServerSecurityService $server,
    ): RedirectResponse {
        $server->deleteBaseline();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Integrity baseline deleted.'),
        ]);
    }

    public function deleteSuspiciousFile(
        Request $request,
        PasakServerSecurityService $server,
    ): RedirectResponse {
        $validated = $request->validate([
            'file_path' => ['required', 'string'],
        ]);

        $result = $server->deleteSuspiciousFile(
            $validated['file_path'],
            auth()->id(),
        );

        return back()->with('toast', [
            'type' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);
    }

    public function downloadSuspiciousFile(
        Request $request,
        PasakServerSecurityService $server,
    ): BinaryFileResponse|RedirectResponse {
        $validated = $request->validate([
            'file_path' => ['required', 'string'],
        ]);

        $filePath = $validated['file_path'];

        if (! $server->isInspectable($filePath)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __(
                    'Berkas tidak ditemukan atau di luar cakupan pemindaian.',
                ),
            ]);
        }

        $fullPath = base_path($filePath);

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('Berkas fisik tidak dapat dibaca di server.'),
            ]);
        }

        $downloadFilename =
            'pasak-sample-'.str_replace(['/', '\\'], '-', $filePath).'.txt';

        return response()->download($fullPath, $downloadFilename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'attachment; filename="'.$downloadFilename.'"',
        ]);
    }

    public function verifySuspiciousFile(
        Request $request,
        PasakServerSecurityService $server,
    ): RedirectResponse {
        $validated = $request->validate([
            'file_path' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $server->verifySuspiciousFile(
            $validated['file_path'],
            auth()->id(),
            $validated['notes'] ?? null,
        );

        return back()->with('toast', [
            'type' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);
    }

    public function unverifySuspiciousFile(
        Request $request,
        PasakServerSecurityService $server,
    ): RedirectResponse {
        $validated = $request->validate([
            'file_path' => ['required', 'string'],
        ]);

        $result = $server->unverifySuspiciousFile($validated['file_path']);

        return back()->with('toast', [
            'type' => $result['success'] ?? false ? 'success' : 'error',
            'message' => $result['message'] ??
                __('Gagal membatalkan verifikasi berkas.'),
        ]);
    }

    public function releaseLockout(
        int|string $id,
        PasakServerSecurityService $server,
    ): RedirectResponse {
        $attempt = PasakLoginAttempt::query()->find($id);

        if ($attempt) {
            $server->releaseLockout($attempt);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Lockout released.'),
        ]);
    }

    /**
     * Simpan pembaruan lokasi fisik server & koordinat Live Threat Map.
     */
    public function updateLocation(
        Request $request,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'server_location_name' => ['nullable', 'string', 'max:255'],
            'server_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'server_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'server_timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $tz = $validated['server_timezone'] ?? null;
        if ($tz !== null && $tz !== '' && ! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Identifier zona waktu tidak valid.'),
                ], 422);
            }

            return back()->withErrors(['server_timezone' => __('Identifier zona waktu tidak valid.')]);
        }

        PasakSetting::setServerLocation(
            $validated['server_location_name'] ?? null,
            isset($validated['server_latitude']) &&
            $validated['server_latitude'] !== '' &&
            $validated['server_latitude'] !== null
                ? (float) $validated['server_latitude']
                : null,
            isset($validated['server_longitude']) &&
            $validated['server_longitude'] !== '' &&
            $validated['server_longitude'] !== null
                ? (float) $validated['server_longitude']
                : null,
            $tz !== '' ? $tz : null,
        );

        $message = __(
            'Lokasi target server berhasil disimpan dan disinkronkan ke Live Threat Map.',
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => PasakSetting::getServerLocation(),
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    /**
     * Bersihkan seluruh cache sistem dan optimasi Laravel (optimize:clear).
     */
    public function optimizeClear(
        Request $request,
    ): JsonResponse|RedirectResponse {
        try {
            Artisan::call('optimize:clear');
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            $output = $e->getMessage();
        }

        $message = __(
            'Cache aplikasi, tampilan (views), routes, dan konfigurasi berhasil dibersihkan.',
        );

        if ($request->expectsJson() || $request->ajax()) {
            session()->flash('toast', [
                'type' => 'success',
                'message' => $message,
            ]);

            return response()->json([
                'ok' => true,
                'message' => $message,
                'output' => $output,
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $message,
        ]);
    }
}

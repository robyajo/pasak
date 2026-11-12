<?php

namespace Internal\Pasak\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Internal\Pasak\Models\PasakLoginAttempt;
use Internal\Pasak\Models\PasakSetting;
use Internal\Pasak\Services\PasakLoginThrottleService;
use Internal\Pasak\Services\PasakServerSecurityService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PasakServerSecurityApiController extends Controller
{
    public function __construct(
        protected PasakServerSecurityService $serverService,
        protected PasakLoginThrottleService $throttleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $force = filter_var(
            $request->input('refresh', false),
            FILTER_VALIDATE_BOOLEAN,
        );
        $report = $this->serverService->scan($force);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function storeBaseline(Request $request): JsonResponse
    {
        $baseline = $this->serverService->createBaseline();

        return response()->json([
            'success' => true,
            'message' => 'Baseline integritas berkas berhasil dibuat.',
            'data' => $baseline,
        ]);
    }

    public function destroyBaseline(Request $request): JsonResponse
    {
        $deleted = $this->serverService->deleteBaseline();

        return response()->json([
            'success' => $deleted,
            'message' => $deleted
                ? 'Baseline integritas berkas berhasil dihapus.'
                : 'Gagal menghapus baseline.',
        ]);
    }

    public function destroySuspiciousFile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_path' => ['required', 'string'],
        ]);

        $result = $this->serverService->deleteSuspiciousFile(
            $validated['file_path'],
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function downloadSuspiciousFile(
        Request $request,
    ): BinaryFileResponse|JsonResponse {
        $filePath = (string) $request->input('file_path', '');
        $fileInfo = $this->serverService->getSuspiciousFileForDownload(
            $filePath,
        );

        if (! $fileInfo) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Berkas tidak ditemukan atau jalur tidak valid.',
                ],
                404,
            );
        }

        return response()->download(
            $fileInfo['absolute_path'],
            $fileInfo['filename'],
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function verifySuspiciousFile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_path' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->serverService->verifySuspiciousFile(
            $validated['file_path'],
            auth()->id(),
            $validated['notes'] ?? null,
            $request->ip(),
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function releaseLockout(
        Request $request,
        int|string $id,
    ): JsonResponse {
        $attempt = PasakLoginAttempt::query()->find($id);

        if (! $attempt) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Data lockout tidak ditemukan.',
                ],
                404,
            );
        }

        $email = $attempt->email;
        $ip = $attempt->ip_address;
        $attempt->delete();

        return response()->json([
            'success' => true,
            'message' => "Lockout untuk {$email} ({$ip}) berhasil dibuka.",
        ]);
    }

    /**
     * Dapatkan konfigurasi lokasi fisik server & Live Threat Map.
     */
    public function getLocation(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PasakSetting::getServerLocation(),
        ]);
    }

    /**
     * Simpan pembaruan lokasi fisik server & koordinat Live Threat Map.
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_location_name' => ['nullable', 'string', 'max:255'],
            'server_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'server_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'server_timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $tz = $validated['server_timezone'] ?? null;
        if ($tz !== null && $tz !== '' && ! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifier zona waktu tidak valid.',
            ], 422);
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

        return response()->json([
            'success' => true,
            'message' => 'Lokasi server berhasil diperbarui.',
            'data' => PasakSetting::getServerLocation(),
        ]);
    }

    /**
     * Bersihkan seluruh cache sistem dan optimasi Laravel (optimize:clear).
     */
    public function optimizeClear(Request $request): JsonResponse
    {
        try {
            Artisan::call('optimize:clear');
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            $output = $e->getMessage();
        }

        return response()->json([
            'ok' => true,
            'message' => __(
                'Cache aplikasi, tampilan (views), routes, dan konfigurasi berhasil dibersihkan.',
            ),
            'output' => $output,
        ]);
    }
}

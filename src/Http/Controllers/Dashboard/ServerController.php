<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Internal\Pasak\Models\LoginAttempt;
use Internal\Pasak\Services\ServerSecurityService;

/**
 * Server audit page for the Inertia + React dashboard.
 */
class ServerController extends Controller
{
    public function index(
        Request $request,
        ServerSecurityService $server,
    ): Response {
        $force = $request->boolean('refresh');

        if ($force) {
            $server->forget();
        }

        return Inertia::render('security/server', [
            'report' => $server->scan($force),
            'environment' => $server->environmentInfo(),
            'baseline' => $server->baselineSummary(),
            'integrity' => $server->integrityReport(),
            'suspicious' => $server->suspiciousFiles($force),
            'lockouts' => $server->activeLockouts(),
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
                'lockoutDestroy' => route(
                    'pasak.dashboard.lockouts.destroy',
                    ['id' => '__ID__'],
                ),
            ],
        ]);
    }

    public function createBaseline(
        ServerSecurityService $server,
    ): RedirectResponse {
        $server->createBaseline(auth()->id());

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Integrity baseline created.'),
        ]);
    }

    public function deleteBaseline(
        ServerSecurityService $server,
    ): RedirectResponse {
        $server->deleteBaseline();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Integrity baseline deleted.'),
        ]);
    }

    public function deleteSuspiciousFile(
        Request $request,
        ServerSecurityService $server,
    ): RedirectResponse {
        $validated = $request->validate([
            'file_path' => ['required', 'string'],
        ]);

        $result = $server->deleteSuspiciousFile(
            $validated['file_path'],
            auth()->id(),
        );

        return back()->with('toast', [
            'type' => $result['success'] ?? false ? 'success' : 'error',
            'message' => $result['message'] ?? __('Unable to delete the file.'),
        ]);
    }

    public function releaseLockout(
        int $id,
        ServerSecurityService $server,
    ): RedirectResponse {
        $attempt = LoginAttempt::query()->find($id);

        if ($attempt) {
            $server->releaseLockout($attempt);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Lockout released.'),
        ]);
    }
}

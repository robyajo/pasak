<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Internal\Pasak\Models\PasakLoginAttempt;
use Internal\Pasak\Services\PasakServerSecurityService;

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

        return $this->dashboardResponse(
            'pages.pasak.server',
            [
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
            ]
        );
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
            'type' => $result['success'] ?? false ? 'success' : 'error',
            'message' => $result['message'] ?? __('Unable to delete the file.'),
        ]);
    }

    public function releaseLockout(
        int $id,
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
}

<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\VersionCheckService;

class SettingController extends Controller
{
    use RespondsWithDashboard;

    public function __construct(
        protected PasakService $securityService,
    ) {}

    /**
     * Render the settings page on the Blade dashboard.
     */
    public function index(VersionCheckService $versionChecker): View
    {
        return $this->dashboardResponse(
            'pages.pasak.settings',
            [
                'settings' => $this->securityService->getSettings(),
                'version' => [
                    'current' => $versionChecker->getCurrentVersion(),
                    'latest' => $versionChecker->getLatestVersion(),
                    'update_available' => $versionChecker->isUpdateAvailable(),
                ],
            ]
        );
    }

    /**
     * Update settings from the dashboard form.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'auto_block_scope' => ['nullable', 'string', 'in:device,ip'],
            'instant_block_scope' => ['nullable', 'string', 'in:device,ip'],
            'auto_block_enabled' => ['nullable', 'boolean'],
            'auto_block_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'auto_block_window' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'auto_block_duration' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'auto_block_levels' => ['nullable', 'array', 'min:1'],
            'auto_block_levels.*' => ['string', 'in:low,medium,high,critical'],
            'instant_block_enabled' => ['nullable', 'boolean'],
            'instant_block_duration' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'block_enforcement' => ['nullable', 'boolean'],
        ]);

        $this->securityService->updateSettings($validated);

        return back()->with('security_message', 'Pengaturan keamanan berhasil diperbarui.');
    }
}

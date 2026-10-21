<?php

namespace Internal\Pasak\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\VersionCheckService;

class PasakSecuritySettingApiController extends Controller
{
    public function __construct(
        protected PasakService $securityService,
    ) {}

    /**
     * Get current security monitor settings.
     */
    public function index(VersionCheckService $versionChecker): JsonResponse
    {
        return response()->json([
            'success' => true,
            'settings' => $this->securityService->getSettings(),
            'version' => [
                'current' => $versionChecker->getCurrentVersion(),
                'latest' => $versionChecker->getLatestVersion(),
                'update_available' => $versionChecker->isUpdateAvailable(),
            ],
        ]);
    }

    /**
     * Update security monitor settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auto_block_scope' => ['nullable', 'string', 'in:device,ip,auto'],
            'instant_block_scope' => ['nullable', 'string', 'in:device,ip'],
            'auto_block_enabled' => ['nullable', 'boolean'],
            'auto_block_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'auto_block_window' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'auto_block_duration' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'auto_block_levels' => ['nullable', 'array', 'min:1'],
            'auto_block_levels.*' => ['string', 'in:low,medium,high,critical'],
            'auto_block_level_thresholds' => ['nullable', 'array'],
            'auto_block_level_thresholds.low' => ['nullable', 'integer', 'min:1', 'max:100'],
            'auto_block_level_thresholds.medium' => ['nullable', 'integer', 'min:1', 'max:100'],
            'auto_block_level_thresholds.high' => ['nullable', 'integer', 'min:1', 'max:100'],
            'auto_block_level_thresholds.critical' => ['nullable', 'integer', 'min:1', 'max:100'],
            'auto_block_combined_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'instant_block_enabled' => ['nullable', 'boolean'],
            'instant_block_duration' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'block_enforcement' => ['nullable', 'boolean'],
            'login_lockout_enabled' => ['nullable', 'boolean'],
            'login_lockout_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'login_lockout_base_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'login_lockout_max_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'login_lockout_decay_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'log_retention_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        $updated = $this->securityService->updateSettings($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan keamanan berhasil diperbarui.',
            'settings' => $updated,
        ]);
    }
}

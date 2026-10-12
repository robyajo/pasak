<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;
use Internal\Pasak\Models\AllowedIp;

class AllowedIpController extends Controller
{
    use RespondsWithDashboard;

    public function index(Request $request): View|Response
    {
        $query = AllowedIp::query()->latest('id');

        $search = (string) $request->query('search', '');
        $status = (string) $request->query('status', '');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'expired') {
            $query->expired();
        } elseif ($status === 'permanent') {
            $query->whereNull('expires_at')->where('is_active', true);
        }

        return $this->dashboardResponse(
            'pages.pasak.allowed-ips',
            'pasak/allowed-ips',
            [
                'entries' => $query->paginate(25)->withQueryString(),
                'stats' => [
                    'total' => AllowedIp::query()->count(),
                    'active' => AllowedIp::query()->active()->count(),
                    'permanent' => AllowedIp::query()->whereNull('expires_at')->where('is_active', true)->count(),
                ],
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                ],
                'urls' => [
                    'index' => route('pasak.dashboard.allowed-ips'),
                    'store' => route('pasak.dashboard.allowed-ips.store'),
                    'toggle' => route('pasak.dashboard.allowed-ips.toggle', ['id' => '__ID__']),
                    'destroy' => route('pasak.dashboard.allowed-ips.destroy', ['id' => '__ID__']),
                ],
            ]
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:45'],
            'label' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'is_permanent' => ['nullable', 'boolean'],
        ]);

        $pattern = trim($validated['ip_address']);

        if (! AllowedIp::isValidPattern($pattern)) {
            return back()->withErrors([
                'ip_address' => __('Format IP tidak valid. Gunakan IP tunggal, wildcard (192.168.1.*), atau CIDR (10.0.0.0/8).'),
            ])->withInput();
        }

        $duration = (int) ($validated['duration_hours'] ?? 0);
        $isPermanent = (bool) ($validated['is_permanent'] ?? ($duration === 0));

        AllowedIp::query()->updateOrCreate(
            ['ip_address' => $pattern],
            [
                'label' => $validated['label'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'allowed_by' => $request->user()?->getKey(),
                'is_active' => true,
                'expires_at' => $isPermanent ? null : now()->addHours($duration),
            ]
        );

        return back()->with('security_message', __('IP :ip berhasil diizinkan.', ['ip' => $pattern]));
    }

    public function toggle(int $id): RedirectResponse
    {
        $allowed = AllowedIp::query()->find($id);

        if (! $allowed) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('Data allowed IP tidak ditemukan.'),
            ]);
        }

        $allowed->is_active = ! $allowed->is_active;
        $allowed->save();

        return back()->with(
            'security_message',
            $allowed->is_active ? __('Izin IP diaktifkan.') : __('Izin IP dinonaktifkan.')
        );
    }

    public function destroy(int $id): RedirectResponse
    {
        $allowed = AllowedIp::query()->find($id);

        if (! $allowed) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('Data allowed IP tidak ditemukan.'),
            ]);
        }

        $allowed->delete();

        return back()->with('security_message', __('IP dihapus dari daftar izin.'));
    }
}

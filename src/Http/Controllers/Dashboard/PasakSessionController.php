<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Internal\Pasak\Models\PasakTrustedIp;
use Internal\Pasak\Models\PasakUserLogin;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\PasakUserLoginService;

/**
 * User session tracking page for the Blade monitoring dashboard.
 */
class PasakSessionController extends Controller
{
    use RespondsWithPasakDashboard;

    public function index(Request $request): View
    {
        $query = PasakUserLogin::query()->with('user')->latest('id');

        $search = (string) $request->query('search', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $perPage = (int) $request->query('per_page', 25);

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('operating_system', 'like', "%{$search}%")
                    ->orWhere('device_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search): void {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($dateFrom !== '') {
            try {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where(function ($q) use ($from): void {
                    $q->where('login_at', '>=', $from)
                        ->orWhere('created_at', '>=', $from);
                });
            } catch (\Throwable) {
            }
        }

        if ($dateTo !== '') {
            try {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where(function ($q) use ($to): void {
                    $q->where('login_at', '<=', $to)
                        ->orWhere('created_at', '<=', $to);
                });
            } catch (\Throwable) {
            }
        }

        return $this->dashboardResponse(
            'pages.pasak.sessions',
            [
                'logins' => $query->paginate($perPage)->withQueryString(),
                'trustedIps' => PasakTrustedIp::query()->with('user')->latest('id')->get(),
                'onlineCount' => PasakUserLogin::query()->active(5)->count(),
                'filters' => [
                    'search' => $search,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'per_page' => $perPage,
                ],
                'urls' => [
                    'index' => route('pasak.dashboard.sessions'),
                    'loginDestroy' => route('pasak.dashboard.user-sessions.destroy', ['id' => '__ID__']),
                    'sessionDestroy' => route('pasak.dashboard.sessions.destroy', ['sessionId' => '__SESSION__']),
                    'trustedDestroy' => route('pasak.dashboard.trusted-ips.destroy', ['id' => '__ID__']),
                    'storeMyIp' => route('pasak.dashboard.trusted-ips.store-my-ip'),
                ],
            ]
        );
    }

    public function destroyLogin(int $id): RedirectResponse
    {
        PasakUserLogin::query()->whereKey($id)->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Login history entry deleted.'),
        ]);
    }

    public function destroySession(string $sessionId, PasakUserLoginService $logins): RedirectResponse
    {
        $logins->logoutSession($sessionId);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Session terminated.'),
        ]);
    }

    public function destroyTrusted(int $id): RedirectResponse
    {
        $trusted = PasakTrustedIp::query()->find($id);
        $ip = $trusted?->ip_address;

        $trusted?->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Trusted IP :ip removed.', ['ip' => $ip ?? '']),
        ]);
    }

    public function storeMyIp(Request $request, PasakService $security): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return back();
        }

        $ip = $security->resolveClientIp($request);
        $userAgent = (string) $request->userAgent();

        PasakTrustedIp::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'ip_address' => $ip,
            ],
            [
                'local_ip' => $security->resolveLocalIp($request),
                'device_id' => $security->resolveDeviceId($request),
                'device_name' => Str::limit($userAgent, 100) ?: __('My device'),
                'operating_system' => $userAgent,
                'is_active' => true,
                'verified_at' => now(),
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('IP :ip saved as trusted.', ['ip' => $ip]),
        ]);
    }
}

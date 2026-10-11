<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Internal\Pasak\Models\TrustedIp;
use Internal\Pasak\Models\UserLogin;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\UserLoginService;

/**
 * User session tracking page for the Inertia + React dashboard.
 */
class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = UserLogin::query()->with('user')->latest('id');

        $search = (string) $request->query('search', '');

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

        return Inertia::render('security/sessions', [
            'logins' => $query->paginate(20)->withQueryString(),
            'trustedIps' => TrustedIp::query()->with('user')->latest('id')->get(),
            'onlineCount' => UserLogin::query()->active(5)->count(),
            'filters' => [
                'search' => $search,
            ],
            'urls' => [
                'index' => route('pasak.dashboard.sessions'),
                'loginDestroy' => route('pasak.dashboard.user-sessions.destroy', ['id' => '__ID__']),
                'sessionDestroy' => route('pasak.dashboard.sessions.destroy', ['sessionId' => '__SESSION__']),
                'trustedDestroy' => route('pasak.dashboard.trusted-ips.destroy', ['id' => '__ID__']),
                'storeMyIp' => route('pasak.dashboard.trusted-ips.store-my-ip'),
            ],
        ]);
    }

    public function destroyLogin(int $id): RedirectResponse
    {
        UserLogin::query()->whereKey($id)->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Login history entry deleted.'),
        ]);
    }

    public function destroySession(string $sessionId, UserLoginService $logins): RedirectResponse
    {
        $logins->logoutSession($sessionId);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Session terminated.'),
        ]);
    }

    public function destroyTrusted(int $id): RedirectResponse
    {
        $trusted = TrustedIp::query()->find($id);
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

        TrustedIp::query()->updateOrCreate(
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

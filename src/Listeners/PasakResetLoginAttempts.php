<?php

namespace Internal\Pasak\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Gate;
use Internal\Pasak\Services\PasakLoginThrottleService;
use Internal\Pasak\Services\PasakService;

/**
 * Mengosongkan riwayat kegagalan login setelah pengguna berhasil masuk,
 * sehingga cooldown bertingkat tidak "menempel" pada pengguna yang sah,
 * serta otomatis membuka blokir IP apabila yang login adalah admin.
 */
class PasakResetLoginAttempts
{
    public function __construct(
        protected PasakLoginThrottleService $throttle,
        protected PasakService $security,
    ) {}

    public function handle(Login $event): void
    {
        $ip = request()->ip();
        $this->throttle->registerSuccess($event->user?->email, $ip);

        // Buka blokir IP otomatis bila yang login adalah administrator.
        if (
            $ip &&
            $event->user &&
            Gate::forUser($event->user)->allows('manage-pasak')
        ) {
            $this->security->unblockIp($ip);
        }
    }
}

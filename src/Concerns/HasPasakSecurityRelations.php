<?php

namespace Internal\Pasak\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Internal\Pasak\Models\PasakAllowedIp;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakIpUnblockRequest;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Models\PasakTrustedIp;
use Internal\Pasak\Models\PasakUserLogin;

trait HasPasakSecurityRelations
{
    public function logins(): HasMany
    {
        return $this->hasMany(PasakUserLogin::class, 'user_id');
    }

    public function trustedIps(): HasMany
    {
        return $this->hasMany(PasakTrustedIp::class, 'user_id');
    }

    public function securityLogs(): HasMany
    {
        return $this->hasMany(PasakLog::class, 'user_id');
    }

    public function pasakLogs(): HasMany
    {
        return $this->securityLogs();
    }

    public function blockedIps(): HasMany
    {
        return $this->hasMany(PasakBlockedIp::class, 'blocked_by');
    }

    public function allowedIps(): HasMany
    {
        return $this->hasMany(PasakAllowedIp::class, 'allowed_by');
    }

    public function resolvedTickets(): HasMany
    {
        return $this->hasMany(PasakIpUnblockRequest::class, 'resolved_by');
    }
}

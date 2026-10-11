<?php

namespace Internal\Pasak\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Internal\Pasak\Models\BlockedIp;
use Internal\Pasak\Models\IpUnblockRequest;
use Internal\Pasak\Models\SecurityLog;
use Internal\Pasak\Models\TrustedIp;
use Internal\Pasak\Models\UserLogin;

trait HasSecurityRelations
{
    public function logins(): HasMany
    {
        return $this->hasMany(UserLogin::class, 'user_id');
    }

    public function trustedIps(): HasMany
    {
        return $this->hasMany(TrustedIp::class, 'user_id');
    }

    public function securityLogs(): HasMany
    {
        return $this->hasMany(SecurityLog::class, 'user_id');
    }

    public function blockedIps(): HasMany
    {
        return $this->hasMany(BlockedIp::class, 'blocked_by');
    }

    public function resolvedTickets(): HasMany
    {
        return $this->hasMany(IpUnblockRequest::class, 'resolved_by');
    }
}

<?php

namespace Internal\Pasak\Services;

/**
 * Legacy alias for PasakLoginThrottleService.
 */
class LoginThrottleService extends PasakLoginThrottleService
{
}

if (! class_exists('Internal\Pasak\Services\LoginThrottleService', false)) {
    class_alias(PasakLoginThrottleService::class, 'Internal\Pasak\Services\LoginThrottleService');
}

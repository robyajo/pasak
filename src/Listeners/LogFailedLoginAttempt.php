<?php

namespace Internal\Pasak\Listeners;

/**
 * Legacy alias for PasakLogFailedLoginAttempt.
 */
class LogFailedLoginAttempt extends PasakLogFailedLoginAttempt
{
}

if (! class_exists('Internal\Pasak\Listeners\LogFailedLoginAttempt', false)) {
    class_alias(PasakLogFailedLoginAttempt::class, 'Internal\Pasak\Listeners\LogFailedLoginAttempt');
}

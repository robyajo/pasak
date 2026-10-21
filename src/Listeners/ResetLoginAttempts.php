<?php

namespace Internal\Pasak\Listeners;

/**
 * Legacy alias for PasakResetLoginAttempts.
 */
class ResetLoginAttempts extends PasakResetLoginAttempts
{
}

if (! class_exists('Internal\Pasak\Listeners\ResetLoginAttempts', false)) {
    class_alias(PasakResetLoginAttempts::class, 'Internal\Pasak\Listeners\ResetLoginAttempts');
}

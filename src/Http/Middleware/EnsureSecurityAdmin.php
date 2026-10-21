<?php

namespace Internal\Pasak\Http\Middleware;

/**
 * Legacy alias for PasakEnsureSecurityAdmin.
 */
class EnsureSecurityAdmin extends PasakEnsureSecurityAdmin {}

if (! class_exists('Internal\Pasak\Http\Middleware\EnsureSecurityAdmin', false)) {
    class_alias(PasakEnsureSecurityAdmin::class, 'Internal\Pasak\Http\Middleware\EnsureSecurityAdmin');
}

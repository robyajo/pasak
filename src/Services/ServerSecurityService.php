<?php

namespace Internal\Pasak\Services;

/**
 * Legacy alias for PasakServerSecurityService.
 */
class ServerSecurityService extends PasakServerSecurityService {}

if (! class_exists('Internal\Pasak\Services\ServerSecurityService', false)) {
    class_alias(PasakServerSecurityService::class, 'Internal\Pasak\Services\ServerSecurityService');
}

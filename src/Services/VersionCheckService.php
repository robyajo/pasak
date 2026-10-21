<?php

namespace Internal\Pasak\Services;

/**
 * Legacy alias for PasakVersionCheckService.
 */
class VersionCheckService extends PasakVersionCheckService {}

if (! class_exists('Internal\Pasak\Services\VersionCheckService', false)) {
    class_alias(PasakVersionCheckService::class, 'Internal\Pasak\Services\VersionCheckService');
}

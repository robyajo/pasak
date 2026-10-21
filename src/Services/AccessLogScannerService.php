<?php

namespace Internal\Pasak\Services;

/**
 * Legacy alias for PasakAccessLogScannerService.
 */
class AccessLogScannerService extends PasakAccessLogScannerService
{
}

if (! class_exists('Internal\Pasak\Services\AccessLogScannerService', false)) {
    class_alias(PasakAccessLogScannerService::class, 'Internal\Pasak\Services\AccessLogScannerService');
}

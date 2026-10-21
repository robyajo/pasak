<?php

namespace Internal\Pasak\Console\Commands;

/**
 * Legacy alias for PasakScanAccessLogsCommand.
 */
class SecurityScanAccessLogs extends PasakScanAccessLogsCommand
{
}

if (! class_exists('Internal\\Pasak\\Console\\Commands\\SecurityScanAccessLogs', false)) {
    class_alias(PasakScanAccessLogsCommand::class, 'Internal\\Pasak\\Console\\Commands\\SecurityScanAccessLogs');
}

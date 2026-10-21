<?php

namespace Internal\Pasak\Console\Commands;

/**
 * Legacy alias for PasakPruneSecurityLogsCommand.
 */
class PruneSecurityLogs extends PasakPruneSecurityLogsCommand {}

if (! class_exists('Internal\\Pasak\\Console\\Commands\\PruneSecurityLogs', false)) {
    class_alias(PasakPruneSecurityLogsCommand::class, 'Internal\\Pasak\\Console\\Commands\\PruneSecurityLogs');
}

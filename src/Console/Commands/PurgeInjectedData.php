<?php

namespace Internal\Pasak\Console\Commands;

/**
 * Legacy alias for PasakPurgeInjectedDataCommand.
 */
class PurgeInjectedData extends PasakPurgeInjectedDataCommand
{
}

if (! class_exists('Internal\\Pasak\\Console\\Commands\\PurgeInjectedData', false)) {
    class_alias(PasakPurgeInjectedDataCommand::class, 'Internal\\Pasak\\Console\\Commands\\PurgeInjectedData');
}

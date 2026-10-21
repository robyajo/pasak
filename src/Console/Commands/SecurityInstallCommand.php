<?php

namespace Internal\Pasak\Console\Commands;

/**
 * Legacy alias for PasakInstallCommand.
 */
class SecurityInstallCommand extends PasakInstallCommand {}

if (! class_exists('Internal\\Pasak\\Console\\Commands\\SecurityInstallCommand', false)) {
    class_alias(PasakInstallCommand::class, 'Internal\\Pasak\\Console\\Commands\\SecurityInstallCommand');
}
